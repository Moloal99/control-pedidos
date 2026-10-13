<?php

namespace App\Servicios;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\EstadoProforma;
use App\Models\NotaLinea;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\SharePoint\Espejo;
use Illuminate\Support\Collection;

/**
 * Un solo lugar que recalcula todo lo derivado de un pedido despues de
 * cualquier movimiento (numerar, despachar, anular, cobrar, editar).
 *
 * En la Power App estos valores se calculaban en pantalla y varias columnas
 * de SharePoint quedaban siempre en 0 (LINEA_CANTIDAD_DESPACHADA,
 * PROFORMA_TOTAL_DESPACHADO) o desactualizadas (PROFORMA_ESTADO,
 * PEDIDO_TOTAL_PAGADO). Aqui se escriben, asi Power BI tambien las ve bien.
 */
class EstadoPedido
{
    public function __construct(private readonly Espejo $espejo) {}

    /**
     * Despachado por linea (solo notas no anuladas). Las lineas de nota que
     * vinieron de SharePoint sin vinculo directo se casan por proforma + codigo.
     *
     * @return Collection<int, float> pedido_linea_id => cantidad
     */
    public static function despachadoPorLinea(Pedido $pedido): Collection
    {
        $lineas = $pedido->lineas()->get();

        $notaLineas = NotaLinea::query()
            ->whereHas('nota', fn ($q) => $q->where('anulada', false)->where('pedido_id', $pedido->getKey()))
            ->get();

        return $lineas->mapWithKeys(function (PedidoLinea $linea) use ($notaLineas) {
            $cantidad = $notaLineas
                ->filter(fn (NotaLinea $nl) => $nl->pedido_linea_id === $linea->getKey()
                    || ($nl->pedido_linea_id === null
                        && $nl->proforma_clave === $linea->proforma_clave
                        && $nl->producto_codigo === $linea->producto_codigo))
                ->sum(fn (NotaLinea $nl) => (float) $nl->cantidad);

            return [$linea->getKey() => round($cantidad, 3)];
        });
    }

    public function recalcular(Pedido $pedido): Pedido
    {
        $pedido->refresh();
        $despachado = self::despachadoPorLinea($pedido);
        $lineas = $pedido->lineas()->get();

        foreach ($lineas as $linea) {
            $valor = $despachado[$linea->getKey()] ?? 0;

            if (abs((float) $linea->cantidad_despachada - $valor) > 0.0005) {
                $linea->cantidad_despachada = $valor;
                $linea->save();
                $this->espejo->actualizar($linea, ['cantidad_despachada']);
            }
        }

        foreach ($pedido->proformas()->get() as $proforma) {
            $propias = $lineas->where('proforma_id', $proforma->getKey());
            $total = round($propias->sum(fn (PedidoLinea $l) => $l->subtotal()), 2);
            $totalDespachado = round($propias->sum(fn (PedidoLinea $l) => ($despachado[$l->getKey()] ?? 0) * (float) $l->precio_unitario), 2);

            $completas = $propias->isNotEmpty() && $propias->every(fn (PedidoLinea $l) => ($despachado[$l->getKey()] ?? 0) + 0.0005 >= (float) $l->cantidad);
            $alguna = $propias->contains(fn (PedidoLinea $l) => ($despachado[$l->getKey()] ?? 0) > 0);

            $estado = match (true) {
                ! $proforma->tieneNumero() => EstadoProforma::SinNumero,
                $completas => EstadoProforma::Despachada,
                $alguna => EstadoProforma::Parcial,
                default => EstadoProforma::Pendiente,
            };

            $proforma->fill(['total' => $total, 'total_despachado' => $totalDespachado, 'estado' => $estado]);

            if ($proforma->isDirty()) {
                $proforma->save();
                $this->espejo->actualizar($proforma);
            }
        }

        $pedido->load('proformas');
        $totalCantidad = $lineas->sum(fn (PedidoLinea $l) => (float) $l->cantidad);
        $totalDespachado = $despachado->sum();

        $estadoDespacho = match (true) {
            $totalCantidad > 0 && $totalDespachado + 0.0005 >= $totalCantidad => EstadoDespacho::Total,
            $totalDespachado > 0 => EstadoDespacho::Parcial,
            $pedido->proformas->contains(fn ($p) => $p->tieneNumero()) => EstadoDespacho::Proformado,
            default => EstadoDespacho::SinProforma,
        };

        // El pedido refleja lo cobrado en sus notas (en la Power App quedaba en 0)
        $pagado = round((float) $pedido->notas()->where('anulada', false)->sum('total_pagado'), 2);
        $total = round((float) $pedido->total, 2);

        $pedido->fill([
            'estado_despacho' => $estadoDespacho,
            'total_pagado' => $pagado,
            'saldo_pendiente' => max(0, round($total - $pagado, 2)),
            'estado_pago' => EstadoPago::segun($total, $pagado),
        ]);

        if ($pedido->isDirty()) {
            $pedido->save();
            $this->espejo->actualizar($pedido);
        }

        return $pedido;
    }
}
