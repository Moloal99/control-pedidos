<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\EstadoProforma;
use App\Enums\FormaPago;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\User;
use App\Models\Visita;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/**
 * Pantalla Pedido › «Guardar pedido».
 *
 * Cabecera + una proforma por almacen + lineas, como unidad atomica. Si algo
 * falla no queda nada a medias (ni en la base ni en SharePoint) y el carrito
 * se conserva para reintentar.
 */
class GuardarPedido
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly Avisos $avisos,
    ) {}

    /**
     * @param  list<array{producto_id:int, cantidad:float|string, precio:float|string}>  $carrito
     */
    public function ejecutar(User $usuario, ?Visita $visita, array $carrito): Pedido
    {
        Falla::si(! $visita, 'Primero registre la visita');
        Falla::si($carrito === [], 'El pedido no tiene productos cargados');
        Falla::si(! $visita->forma_pago, 'El pedido no tiene forma de pago: vuelva a la visita y elija Crédito o Contado');
        Falla::si(! $usuario->puedeVender(), 'Su rol no permite cargar pedidos.');

        $productos = Producto::query()->whereIn('id', array_column($carrito, 'producto_id'))->get()->keyBy('id');
        $vistos = [];
        $items = [];

        foreach ($carrito as $item) {
            $producto = $productos[$item['producto_id']] ?? null;
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio'];

            Falla::si(! $producto, 'Uno de los productos del pedido ya no existe en el catálogo.');
            Falla::si(isset($vistos[$producto->getKey()]), "«{$producto->descripcion}» está dos veces: deje una sola línea y ajuste la cantidad.");
            Falla::si($cantidad < 1, 'La cantidad tiene que ser 1 o más');
            Falla::si($producto->esPorPieza() && floor($cantidad) != $cantidad, 'Las piezas se venden enteras: la cantidad no puede tener decimales');
            Falla::si($precio <= 0, 'El precio unitario tiene que ser mayor a cero');

            $vistos[$producto->getKey()] = true;
            $items[] = ['producto' => $producto, 'almacen' => $producto->almacen(), 'cantidad' => $cantidad, 'precio' => $precio];
        }

        $vendedor = $visita->vendedor ?? $usuario;

        $pedido = $this->espejo->operacion(function () use ($usuario, $visita, $vendedor, $items) {
            $ahora = now();
            $numero = $this->numero($vendedor->codigo ?? 'SC', $ahora);
            $credito = $visita->forma_pago === FormaPago::Credito;
            $dias = $credito ? (int) $visita->dias_credito : null;
            $total = round(array_sum(array_map(fn ($i) => $i['cantidad'] * $i['precio'], $items)), 2);

            $pedido = Pedido::query()->create([
                'numero' => $numero,
                'visita_id' => $visita->getKey(),
                'cliente_id' => $visita->cliente_id,
                'cliente_codigo' => $visita->cliente_codigo,
                'cliente_nombre' => $visita->cliente_nombre,
                'vendedor_id' => $vendedor->getKey(),
                'vendedor_codigo' => $vendedor->codigo,
                'vendedor_nombre' => $vendedor->name,
                'cargado_por_id' => $usuario->getKey(),
                'cargado_por' => $vendedor->getKey() !== $usuario->getKey() ? $usuario->name : '',
                'tipo_contacto' => $visita->tipo_contacto,
                'forma_pago' => $visita->forma_pago,
                'dias_credito' => $dias,
                'fecha' => $ahora,
                'fecha_vencimiento' => $credito ? $ahora->copy()->addDays($dias) : $ahora,
                'total' => $total,
                'total_pagado' => 0,
                'saldo_pendiente' => $total,
                'estado_pago' => EstadoPago::Pendiente,
                // Se escribe explicito: la Power App dependia del valor por omision de la lista
                'estado_despacho' => EstadoDespacho::SinProforma,
            ]);
            $this->espejo->crear($pedido);

            $porAlmacen = collect($items)->groupBy(fn ($i) => $i['almacen']->codigo)->sortKeys();

            foreach ($porAlmacen as $codigo => $grupo) {
                $almacen = $grupo->first()['almacen'];

                $proforma = Proforma::query()->create([
                    'pedido_id' => $pedido->getKey(),
                    'pedido_numero' => $numero,
                    'clave' => $numero.'-'.$codigo,
                    'almacen_codigo' => $codigo,
                    'almacen' => $almacen->nombre,
                    'fecha' => $ahora,
                    'total' => round($grupo->sum(fn ($i) => $i['cantidad'] * $i['precio']), 2),
                    'total_despachado' => 0,
                    'estado' => EstadoProforma::SinNumero,
                ]);
                $proforma->setRelation('pedido', $pedido->load('cliente'));
                $this->espejo->crear($proforma);

                foreach ($grupo as $item) {
                    $linea = PedidoLinea::query()->create(self::fotoProducto($item['producto']) + [
                        'pedido_id' => $pedido->getKey(),
                        'proforma_id' => $proforma->getKey(),
                        'pedido_numero' => $numero,
                        'proforma_clave' => $proforma->clave,
                        'almacen' => $almacen->nombre,
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'cantidad_despachada' => 0,
                    ]);
                    $this->espejo->crear($linea);
                }
            }

            Rastro::registrar(
                AccionBitacora::Crear,
                "Guardó el pedido {$numero} de {$visita->cliente_nombre}: ".count($items).' producto(s) · '.$porAlmacen->count().' almacén(es) · Bs '.number_format($total, 2),
                'Pedido', $pedido->getKey(),
                [
                    'total' => $total,
                    'forma_pago' => $visita->forma_pago?->value,
                    'almacenes' => $porAlmacen->keys()->all(),
                    'vendedor' => $vendedor->name,
                    'productos' => array_map(fn ($i) => ['codigo' => $i['producto']->codigo, 'cantidad' => $i['cantidad'], 'precio' => $i['precio']], $items),
                ],
                $numero,
            );

            return $pedido;
        });

        $this->avisos->nuevoPedido($pedido, $usuario);

        return $pedido;
    }

    /**
     * Datos del producto que se copian en la linea al momento de la venta.
     *
     * @return array<string, mixed>
     */
    public static function fotoProducto(Producto $producto): array
    {
        return [
            'producto_id' => $producto->getKey(),
            'producto_codigo' => $producto->codigo,
            'producto_descripcion' => $producto->descripcion,
            'producto_unidad' => $producto->unidadNormalizada(),
            'producto_micronaje' => $producto->micronaje,
            'producto_peso_promedio' => $producto->peso_promedio ?? 0,
            'producto_bolsas_por_paquete' => $producto->bolsas_por_paquete,
            'producto_paquetes_por_jaba' => $producto->paquetes_por_jaba,
            'producto_jabitas_por_jaba' => $producto->jabitas_por_jaba,
            'producto_jabas_por_bulto' => $producto->jabas_por_bulto,
            'producto_peso_por_bolsa' => $producto->peso_por_bolsa,
            'producto_peso_por_bulto' => $producto->peso_por_bulto,
            'producto_peso_por_paquete' => $producto->peso_por_paquete,
            'producto_peso_por_jaba' => $producto->peso_por_jaba,
        ];
    }

    /** PED-{codigo}-{yyyymmdd-hhmmss}, unico aunque dos se guarden en el mismo segundo. */
    private function numero(string $codigo, \Carbon\CarbonInterface $momento): string
    {
        $base = 'PED-'.$codigo.'-'.$momento->format('Ymd-His');
        $numero = $base;
        $n = 2;

        while (Pedido::query()->where('numero', $numero)->exists()) {
            $numero = $base.'-'.$n++;
        }

        return $numero;
    }
}
