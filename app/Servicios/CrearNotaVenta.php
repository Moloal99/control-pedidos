<?php

namespace App\Servicios;

use App\Enums\AccionBitacora;
use App\Enums\EstadoPago;
use App\Models\NotaLinea;
use App\Models\NotaVenta;
use App\Models\PedidoLinea;
use App\Models\Proforma;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;
use Illuminate\Http\UploadedFile;

/**
 * Detalles › «Despachar»: crea la nota de venta con sus lineas y el
 * comprobante. Bloquea la proforma mientras despacha para que dos personas
 * no despachen lo mismo a la vez (la Power App no lo impedia).
 */
class CrearNotaVenta
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly EstadoPedido $estado,
    ) {}

    /**
     * @param  array<int|string, mixed>  $cantidades  pedido_linea_id => cantidad a despachar
     */
    public function ejecutar(User $usuario, Proforma $proforma, ?string $numero, array $cantidades, ?UploadedFile $comprobante): NotaVenta
    {
        $numero = trim((string) $numero);
        $pedido = $proforma->pedido;

        Falla::si(! $usuario->puedeDespachar($pedido), 'Usted no puede despachar este pedido.');
        Falla::si(! $proforma->tieneNumero(), 'Cargue primero el número de esta proforma para poder despachar.');
        Falla::si($numero === '', 'Escriba el número de la nota de venta', 'numero');

        $cantidades = collect($cantidades)->map(fn ($c) => $c === null || $c === '' ? 0.0 : (float) $c);

        Falla::si($cantidades->sum() <= 0, 'Indique al menos una cantidad a despachar', 'cantidades');
        Falla::si($cantidades->contains(fn ($c) => $c < 0), 'No se admiten cantidades negativas', 'cantidades');
        // Unico en toda la empresa: notas, lineas y cobranzas se vinculan por este numero
        Falla::si(NotaVenta::query()->where('numero', $numero)->exists(), 'Ya existe una nota de venta con ese número', 'numero');
        Falla::si(! $comprobante, 'Adjunte el comprobante de la nota de venta', 'comprobante');

        $archivo = Comprobantes::guardar($comprobante, 'notas', 'nota-'.$numero);

        try {
            return $this->guardar($usuario, $proforma, $numero, $cantidades, $archivo);
        } catch (\Throwable $e) {
            // Si la nota no se creo, el comprobante no queda huerfano
            Comprobantes::eliminar($archivo['ruta']);

            throw $e;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, float>  $cantidades
     * @param  array{nombre:string, ruta:string, contenido:string}  $archivo
     */
    private function guardar(User $usuario, Proforma $proforma, string $numero, $cantidades, array $archivo): NotaVenta
    {
        $pedido = $proforma->pedido;

        return $this->espejo->operacion(function () use ($usuario, $proforma, $pedido, $numero, $cantidades, $archivo) {
            // Bloqueo: nadie mas despacha esta proforma hasta terminar
            Proforma::query()->whereKey($proforma->getKey())->lockForUpdate()->first();

            $despachado = EstadoPedido::despachadoPorLinea($pedido);
            $lineas = PedidoLinea::query()->where('proforma_id', $proforma->getKey())->get()->keyBy('id');
            $enviar = [];

            foreach ($cantidades as $lineaId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $linea = $lineas[(int) $lineaId] ?? null;
                Falla::si(! $linea, 'Uno de los productos no pertenece a esta proforma.', 'cantidades');

                $pendiente = round((float) $linea->cantidad - ($despachado[$linea->getKey()] ?? 0), 3);

                Falla::si($cantidad > $pendiente + 0.0005, 'Hay productos donde la cantidad supera lo pendiente', 'cantidades');
                Falla::si($linea->esPorPieza() && floor($cantidad) != $cantidad, 'Las unidades PZA deben ser números enteros', 'cantidades');

                $enviar[] = [$linea, $cantidad];
            }

            $total = round(array_sum(array_map(fn ($e) => $e[1] * (float) $e[0]->precio_unitario, $enviar)), 2);

            $nota = NotaVenta::query()->create([
                'numero' => $numero,
                'proforma_id' => $proforma->getKey(),
                'pedido_id' => $pedido->getKey(),
                'proforma_clave' => $proforma->clave,
                'proforma_numero' => $proforma->numero,
                'pedido_numero' => $pedido->numero,
                'cliente_id' => $pedido->cliente_id,
                'cliente_codigo' => $pedido->cliente_codigo,
                'cliente_nombre' => $pedido->cliente_nombre,
                'vendedor_codigo' => $pedido->vendedor_codigo,
                'vendedor_nombre' => $pedido->vendedor_nombre,
                'almacen' => $proforma->almacen,
                'fecha' => now(),
                'total' => $total,
                'total_pagado' => 0,
                'saldo_pendiente' => $total,
                'estado_pago' => EstadoPago::Pendiente,
                'forma_pago' => $pedido->forma_pago,
                'dias_credito' => $pedido->dias_credito,
                'fecha_vencimiento' => $pedido->fecha_vencimiento,
                'anulada' => false,
                'comprobante_nombre' => $archivo['nombre'],
                'comprobante_ruta' => $archivo['ruta'],
                'creado_por' => $usuario->name,
            ]);
            $this->espejo->crear($nota);
            $this->espejo->adjuntar($nota, $archivo['nombre'], $archivo['contenido']);

            foreach ($enviar as [$linea, $cantidad]) {
                $notaLinea = NotaLinea::query()->create([
                    'nota_id' => $nota->getKey(),
                    'pedido_linea_id' => $linea->getKey(),
                    'nota_numero' => $numero,
                    'proforma_clave' => $proforma->clave,
                    'producto_codigo' => $linea->producto_codigo,
                    'producto_descripcion' => $linea->producto_descripcion,
                    'producto_unidad' => $linea->producto_unidad,
                    'almacen' => $linea->almacen,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $linea->precio_unitario,
                    'producto_peso_promedio' => $linea->producto_peso_promedio,
                ]);
                $this->espejo->crear($notaLinea);
            }

            $this->estado->recalcular($pedido);

            Rastro::registrar(
                AccionBitacora::Despachar,
                "Creó la nota de venta {$numero} ({$proforma->almacen}) por Bs ".number_format($total, 2).' · '.count($enviar).' producto(s)',
                'NotaVenta', $nota->getKey(),
                [
                    'proforma' => $proforma->numero,
                    'total' => $total,
                    'productos' => array_map(fn ($e) => ['codigo' => $e[0]->producto_codigo, 'cantidad' => $e[1]], $enviar),
                ],
                $pedido->numero,
                $usuario,
            );

            return $nota;
        });
    }
}
