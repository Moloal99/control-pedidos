<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Enums\EstadoCobranza;
use App\Enums\EstadoPago;
use App\Models\Cobranza;
use App\Models\NotaVenta;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/**
 * Detalles › Cobranzas › «Confirmar cobro». Confirmar y descontar del saldo
 * de la nota es una sola operacion (en la Power App eran dos pasos y podia
 * quedar confirmada sin descontar).
 */
class ConfirmarCobranza
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly EstadoPedido $estado,
        private readonly Avisos $avisos,
    ) {}

    public function ejecutar(User $usuario, Cobranza $cobranza, ?string $numero): Cobranza
    {
        $numero = mb_strtoupper(trim((string) $numero));

        Falla::si(! $usuario->puedeConfirmarCobranzas(), 'Su rol no permite confirmar cobranzas.');

        $cobranza = $this->espejo->operacion(function () use ($usuario, $cobranza, $numero) {
            /** @var Cobranza|null $viva */
            $viva = Cobranza::query()->whereKey($cobranza->getKey())->lockForUpdate()->first();
            Falla::si(! $viva, 'Esa cobranza ya no existe. Refresque la lista.');
            $this->espejo->refrescar($viva);

            Falla::si($viva->estaConfirmada(), "Esta cobranza ya fue confirmada con el N° {$viva->numero}. No se volvió a sumar.");
            Falla::si(! $viva->hubo_pago, 'Es una gestión sin pago: no hay nada que confirmar.');
            Falla::si($numero === '', 'Escriba el número de nota de cobranza que dio el sistema', 'numero');
            Falla::si(mb_strlen($numero) < 3, 'El número de cobranza debe tener al menos 3 caracteres', 'numero');
            Falla::si(Cobranza::query()->where('numero', $numero)->exists(), 'Ya existe una cobranza registrada con ese número', 'numero');

            /** @var NotaVenta|null $nota */
            $nota = $viva->nota_id ? NotaVenta::query()->whereKey($viva->nota_id)->lockForUpdate()->first() : null;
            Falla::si(! $nota, "No se encontró la nota {$viva->nota_numero} para actualizar el saldo.");
            $this->espejo->refrescar($nota);

            $viva->fill([
                'numero' => $numero,
                'estado' => EstadoCobranza::Confirmada,
                'confirmada_por' => $usuario->name,
                'fecha_confirmacion' => now(),
            ])->save();
            $this->espejo->actualizar($viva);

            $pagado = round((float) $nota->total_pagado + (float) $viva->monto, 2);
            $saldo = max(0, round((float) $nota->total - $pagado, 2));

            $nota->fill([
                'total_pagado' => $pagado,
                'saldo_pendiente' => $saldo,
                'estado_pago' => $saldo <= 0 ? EstadoPago::Pagado : EstadoPago::Parcial,
            ])->save();
            $this->espejo->actualizar($nota);

            if ($nota->pedido) {
                $this->estado->recalcular($nota->pedido);
            }

            Rastro::registrar(
                AccionBitacora::Confirmar,
                "Confirmó el cobro {$numero} de Bs ".number_format((float) $viva->monto, 2)." sobre la nota {$nota->numero}. Saldo: Bs ".number_format($saldo, 2),
                'Cobranza', $viva->getKey(),
                ['numero' => $numero, 'monto' => (float) $viva->monto, 'pagado' => $pagado, 'saldo' => $saldo],
                $nota->pedido_numero,
                $usuario,
            );

            return $viva;
        });

        $this->avisos->cobroConfirmado($cobranza->refresh(), $usuario);

        return $cobranza;
    }
}
