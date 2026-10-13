<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Models\Cobranza;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/**
 * Correccion del N° de cobranza ya confirmada. SharePoint solo guarda el
 * ultimo numero anterior; la bitacora guarda el historial completo.
 */
class CorregirNumeroCobranza
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly Avisos $avisos,
    ) {}

    public function ejecutar(User $usuario, Cobranza $cobranza, ?string $numero, ?string $motivo): Cobranza
    {
        $numero = mb_strtoupper(trim((string) $numero));
        $motivo = trim((string) $motivo);
        $anterior = (string) $cobranza->numero;

        Falla::si(! $usuario->puedeConfirmarCobranzas(), 'Su rol no permite corregir cobranzas.');
        Falla::si(! $cobranza->estaConfirmada(), 'Solo se corrige el número de una cobranza confirmada.');
        Falla::si($numero === '', 'Escriba el número correcto', 'numero');
        Falla::si($numero === mb_strtoupper($anterior), 'El número nuevo es igual al actual', 'numero');
        Falla::si(Cobranza::query()->where('numero', $numero)->whereKeyNot($cobranza->getKey())->exists(), 'Ya existe otra cobranza con ese número', 'numero');
        Falla::si($motivo === '', 'Escriba el motivo de la corrección', 'motivo');
        Falla::si(mb_strlen($motivo) < 10, 'El motivo tiene que ser más específico (al menos 10 caracteres)', 'motivo');

        $this->espejo->operacion(function () use ($usuario, $cobranza, $numero, $motivo, $anterior) {
            $cobranza->fill([
                'numero' => $numero,
                'numero_anterior' => $anterior,
                'numero_motivo' => $motivo,
                'numero_modif_por' => $usuario->name,
                'fecha_modif_numero' => now(),
            ])->save();
            $this->espejo->actualizar($cobranza);

            Rastro::registrar(
                AccionBitacora::Editar,
                "Corrigió el N° de cobranza de la nota {$cobranza->nota_numero}: {$anterior} → {$numero}",
                'Cobranza', $cobranza->getKey(),
                ['anterior' => $anterior, 'nuevo' => $numero, 'motivo' => $motivo],
                $cobranza->pedido_numero,
                $usuario,
            );
        });

        $this->avisos->correccionNumero($cobranza->refresh(), $anterior, $usuario);

        return $cobranza;
    }
}
