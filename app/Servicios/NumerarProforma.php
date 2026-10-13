<?php

namespace App\Servicios;

use App\Enums\AccionBitacora;
use App\Models\Proforma;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/** Detalles › Proformas › «N° de proforma» (numero tomado del ERP). */
class NumerarProforma
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly EstadoPedido $estado,
    ) {}

    public function ejecutar(User $usuario, Proforma $proforma, ?string $numero): Proforma
    {
        $numero = trim((string) $numero);
        $pedido = $proforma->pedido;

        Falla::si(! $usuario->puedeDespachar($pedido), 'Usted no puede numerar las proformas de este pedido.');
        Falla::si($numero === '', 'Escriba el número de la proforma antes de guardar', 'numero');
        Falla::si($proforma->bloqueada(), 'Esta proforma ya tiene notas de venta: su número no se puede cambiar.', 'numero');
        Falla::si(
            Proforma::query()->where('numero', $numero)->whereKeyNot($proforma->getKey())->exists(),
            "El número de proforma {$numero} ya está asignado a otra proforma.",
            'numero',
        );

        return $this->espejo->operacion(function () use ($usuario, $proforma, $pedido, $numero) {
            $anterior = $proforma->numero;
            $proforma->numero = $numero;
            $proforma->save();
            $this->espejo->actualizar($proforma, ['numero']);

            $this->estado->recalcular($pedido);

            Rastro::registrar(
                AccionBitacora::Numerar,
                ($anterior ? "Cambió el número de la proforma {$proforma->almacen} de {$anterior} a {$numero}" : "Numeró la proforma {$proforma->almacen} con el N° {$numero}"),
                'Proforma', $proforma->getKey(),
                ['anterior' => $anterior, 'nuevo' => $numero, 'clave' => $proforma->clave],
                $pedido->numero,
                $usuario,
            );

            return $proforma->refresh();
        });
    }
}
