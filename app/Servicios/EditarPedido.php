<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Enums\EstadoProforma;
use App\Enums\FormaPago;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Almacenes;
use App\Support\Rastro;

/**
 * Detalles › «Editar pedido». Solo se tocan las lineas de proformas sin
 * notas activas. Todo o nada (en la Power App la cabecera podia quedar
 * guardada con las lineas viejas) y se recalcula el estado de despacho.
 */
class EditarPedido
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly EstadoPedido $estado,
        private readonly Avisos $avisos,
    ) {}

    /**
     * @param  array{forma_pago:?string, dias_credito:mixed}  $cabecera
     * @param  list<array{id?:int|null, producto_id:int, cantidad:mixed, precio:mixed}>  $lineas  estado deseado
     * @return array{pedido:Pedido, cambios:array<string,mixed>}
     */
    public function ejecutar(User $usuario, Pedido $pedido, array $cabecera, array $lineas): array
    {
        Falla::si(! $usuario->puedeEditar($pedido), 'Usted no puede editar este pedido.');

        $forma = FormaPago::tryFrom((string) ($cabecera['forma_pago'] ?? ''));
        $dias = (int) ($cabecera['dias_credito'] ?? 0);

        Falla::si($lineas === [], 'El pedido tiene que tener al menos un producto');
        Falla::si(collect($lineas)->contains(fn ($l) => (float) $l['cantidad'] < 1), 'Hay productos con cantidad menor a 1');
        Falla::si(collect($lineas)->contains(fn ($l) => (float) $l['precio'] <= 0), 'Hay productos con precio cero o vacío');
        Falla::si(! $forma, 'Falta la forma de pago', 'forma_pago');
        Falla::si($forma === FormaPago::Credito && $dias < 1, 'Los días de crédito tienen que ser 1 o más', 'dias_credito');
        Falla::si(collect($lineas)->pluck('producto_id')->duplicates()->isNotEmpty(), 'Ese producto ya está en el pedido: cambie la cantidad en su línea');

        $resultado = $this->espejo->operacion(function () use ($usuario, $pedido, $forma, $dias, $lineas) {
            Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->first();
            $pedido->refresh();

            $actuales = $pedido->lineas()->with('proforma')->get()->keyBy('id');
            $bloqueadas = $pedido->proformas()->get()
                ->filter(fn (Proforma $p) => $p->bloqueada())
                ->pluck('clave')->all();
            $productos = Producto::query()->whereIn('id', array_column($lineas, 'producto_id'))->get()->keyBy('id');

            $antes = [
                'forma_pago' => $pedido->forma_pago,
                'dias' => $pedido->dias_credito,
                'vence' => $pedido->fecha_vencimiento,
            ];
            $cambios = [];
            $conservadas = [];

            // ---- existentes: modificar ----
            foreach ($lineas as $deseada) {
                $id = $deseada['id'] ?? null;

                if (! $id || ! isset($actuales[$id])) {
                    continue;
                }

                $linea = $actuales[$id];
                $conservadas[] = $id;
                $cantidad = (float) $deseada['cantidad'];
                $precio = (float) $deseada['precio'];

                if (abs((float) $linea->cantidad - $cantidad) < 0.0005 && abs((float) $linea->precio_unitario - $precio) < 0.00005) {
                    continue;
                }

                Falla::si(in_array($linea->proforma_clave, $bloqueadas, true), "«{$linea->producto_descripcion}» está en un almacén con notas de venta: no se puede modificar.");
                Falla::si($linea->esPorPieza() && floor($cantidad) != $cantidad, 'Las piezas se venden enteras: hay una cantidad con decimales');

                $cambios[$linea->proforma_clave]['modificados'][] = [
                    'descripcion' => $linea->producto_descripcion, 'codigo' => $linea->producto_codigo, 'unidad' => $linea->producto_unidad,
                    'cantidad_antes' => (float) $linea->cantidad, 'cantidad' => $cantidad,
                    'precio_antes' => (float) $linea->precio_unitario, 'precio' => $precio,
                ];

                $linea->fill(['cantidad' => $cantidad, 'precio_unitario' => $precio])->save();
                $this->espejo->actualizar($linea, ['cantidad', 'precio_unitario']);
            }

            // ---- eliminadas ----
            foreach ($actuales->except($conservadas) as $linea) {
                Falla::si(in_array($linea->proforma_clave, $bloqueadas, true), "«{$linea->producto_descripcion}» está en un almacén con notas de venta: no se puede quitar.");

                $cambios[$linea->proforma_clave]['eliminados'][] = [
                    'descripcion' => $linea->producto_descripcion, 'codigo' => $linea->producto_codigo, 'unidad' => $linea->producto_unidad,
                    'cantidad' => (float) $linea->cantidad, 'precio' => (float) $linea->precio_unitario,
                ];

                $this->espejo->eliminar($linea);
                $linea->delete();
            }

            // ---- nuevas ----
            foreach ($lineas as $deseada) {
                if (! empty($deseada['id']) && isset($actuales[$deseada['id']])) {
                    continue;
                }

                $producto = $productos[$deseada['producto_id']] ?? null;
                Falla::si(! $producto, 'Falta elegir el producto');

                $cantidad = (float) $deseada['cantidad'];
                Falla::si($producto->esPorPieza() && floor($cantidad) != $cantidad, 'Las piezas se venden enteras: la cantidad no puede tener decimales');

                $almacen = $producto->almacen();
                $clave = $pedido->numero.'-'.$almacen->codigo;
                Falla::si(in_array($clave, $bloqueadas, true), "El {$almacen->nombre} ya tiene notas de venta: no se le pueden agregar productos");

                $proforma = Proforma::query()->where('clave', $clave)->first();

                if (! $proforma) {
                    $proforma = Proforma::query()->create([
                        'pedido_id' => $pedido->getKey(),
                        'pedido_numero' => $pedido->numero,
                        'clave' => $clave,
                        'almacen_codigo' => $almacen->codigo,
                        'almacen' => $almacen->nombre,
                        'fecha' => now(),
                        'total' => 0,
                        'total_despachado' => 0,
                        'estado' => EstadoProforma::SinNumero,
                    ]);
                    $proforma->setRelation('pedido', $pedido->load('cliente'));
                    $this->espejo->crear($proforma);
                }

                $linea = PedidoLinea::query()->create(GuardarPedido::fotoProducto($producto) + [
                    'pedido_id' => $pedido->getKey(),
                    'proforma_id' => $proforma->getKey(),
                    'pedido_numero' => $pedido->numero,
                    'proforma_clave' => $clave,
                    'almacen' => $almacen->nombre,
                    'cantidad' => $cantidad,
                    'precio_unitario' => (float) $deseada['precio'],
                    'cantidad_despachada' => 0,
                ]);
                $this->espejo->crear($linea);

                $cambios[$clave]['nuevos'][] = [
                    'descripcion' => $producto->descripcion, 'codigo' => $producto->codigo, 'unidad' => $producto->unidadNormalizada(),
                    'cantidad' => $cantidad, 'precio' => (float) $deseada['precio'],
                ];
            }

            // ---- cabecera ----
            $credito = $forma === FormaPago::Credito;
            $nuevosDias = $credito ? $dias : null;
            $vence = $credito ? $pedido->fecha->copy()->addDays($dias) : $pedido->fecha->copy();
            $cambioCabecera = [];

            if ($antes['forma_pago'] !== $forma) {
                $cambioCabecera['forma_pago'] = [$antes['forma_pago']?->value, $forma->value];
            }

            if ((int) $antes['dias'] !== (int) $nuevosDias) {
                $cambioCabecera['dias'] = [$antes['dias'], $nuevosDias, $antes['vence'], $vence];
            }

            Falla::si($cambios === [] && $cambioCabecera === [], 'No hay cambios para guardar.');

            $total = round((float) $pedido->lineas()->get()->sum(fn (PedidoLinea $l) => $l->subtotal()), 2);

            $pedido->fill([
                'forma_pago' => $forma,
                'dias_credito' => $nuevosDias,
                'fecha_vencimiento' => $vence,
                'total' => $total,
                'modificado_por' => $usuario->name,
                'fecha_modificacion' => now(),
            ])->save();
            $this->espejo->actualizar($pedido);

            // Proformas sin numero que quedaron vacias se eliminan
            foreach ($pedido->proformas()->get() as $proforma) {
                if (! $proforma->tieneNumero() && ! $proforma->lineas()->exists() && ! $proforma->notas()->exists()) {
                    $this->espejo->eliminar($proforma);
                    $proforma->delete();
                }
            }

            $this->estado->recalcular($pedido);

            Rastro::registrar(
                AccionBitacora::Editar,
                "Modificó el pedido {$pedido->numero}: ".$this->resumen($cambios, $cambioCabecera),
                'Pedido', $pedido->getKey(),
                ['lineas' => $cambios, 'cabecera' => array_map(fn ($c) => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $c), $cambioCabecera)],
                $pedido->numero,
                $usuario,
            );

            return ['pedido' => $pedido->refresh(), 'cambios' => ['lineas' => $cambios, 'cabecera' => $cambioCabecera]];
        });

        $this->avisos->proformasModificadas($resultado['pedido'], $resultado['cambios'], $usuario);

        return $resultado;
    }

    /**
     * @param  array<string, array<string, list<array<string,mixed>>>>  $cambios
     * @param  array<string, mixed>  $cabecera
     */
    private function resumen(array $cambios, array $cabecera): string
    {
        $partes = [];
        $cuenta = fn (string $tipo) => collect($cambios)->sum(fn ($c) => count($c[$tipo] ?? []));

        if ($n = $cuenta('nuevos')) {
            $partes[] = "{$n} producto(s) nuevo(s)";
        }
        if ($n = $cuenta('modificados')) {
            $partes[] = "{$n} modificado(s)";
        }
        if ($n = $cuenta('eliminados')) {
            $partes[] = "{$n} eliminado(s)";
        }
        if (isset($cabecera['forma_pago'])) {
            $partes[] = 'forma de pago '.$cabecera['forma_pago'][0].' → '.$cabecera['forma_pago'][1];
        }
        if (isset($cabecera['dias'])) {
            $partes[] = 'días de crédito '.($cabecera['dias'][0] ?? 0).' → '.($cabecera['dias'][1] ?? 0);
        }

        return implode(', ', $partes) ?: 'sin cambios';
    }

    /** Almacen de un producto (para la vista de edicion). */
    public static function claveDe(Pedido $pedido, Producto $producto): string
    {
        return $pedido->numero.'-'.Almacenes::paraProducto($producto->codigo)->codigo;
    }
}
