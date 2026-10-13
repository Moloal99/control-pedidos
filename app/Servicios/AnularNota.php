<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Models\Cobranza;
use App\Models\NotaVenta;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/**
 * Anulacion de nota de venta: las cantidades vuelven a quedar pendientes.
 * Irreversible desde el sistema, con motivo obligatorio.
 */
class AnularNota
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly EstadoPedido $estado,
        private readonly Avisos $avisos,
    ) {}

    public function ejecutar(User $usuario, NotaVenta $nota, ?string $motivo): NotaVenta
    {
        $motivo = trim((string) $motivo);
        $pedido = $nota->pedido;

        Falla::si(! $pedido || ! $usuario->puedeDespachar($pedido), 'Usted no puede anular notas de este pedido.');
        Falla::si($nota->anulada, 'Esta nota ya está anulada');
        // Las gestiones «sin pago» son solo registro de visita: no impiden anular
        Falla::si(
            Cobranza::query()->where('nota_id', $nota->getKey())->where('hubo_pago', true)->exists(),
            'Esta nota tiene cobranzas registradas. No se puede anular.',
        );
        Falla::si($motivo === '', 'Escriba el motivo de la anulación', 'motivo');
        Falla::si(mb_strlen($motivo) < 10, 'El motivo tiene que ser más específico (al menos 10 caracteres)', 'motivo');

        $this->espejo->operacion(function () use ($usuario, $nota, $pedido, $motivo) {
            $nota->fill([
                'anulada' => true,
                'anulada_motivo' => $motivo,
                'anulada_por' => $usuario->name,
                'anulada_fecha' => now(),
            ])->save();
            $this->espejo->actualizar($nota);

            $this->estado->recalcular($pedido);

            Rastro::registrar(
                AccionBitacora::Anular,
                "Anuló la nota de venta {$nota->numero} ({$nota->almacen}) por Bs ".number_format((float) $nota->total, 2),
                'NotaVenta', $nota->getKey(),
                ['motivo' => $motivo, 'total' => (float) $nota->total],
                $pedido->numero,
                $usuario,
            );
        });

        $this->avisos->notaAnulada($nota->refresh(), $usuario);

        return $nota;
    }
}
