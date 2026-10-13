<?php

namespace App\Enums;

/** PROFORMA_ESTADO. */
enum EstadoProforma: string
{
    use ConEtiqueta;

    case SinNumero = 'Sin número';
    case Pendiente = 'Pendiente';
    case Parcial = 'Parcial';
    case Despachada = 'Despachada';

    public function tono(): string
    {
        return match ($this) {
            self::SinNumero => 'ambar',
            self::Pendiente => 'azul',
            self::Parcial => 'naranja',
            self::Despachada => 'verde',
        };
    }
}
