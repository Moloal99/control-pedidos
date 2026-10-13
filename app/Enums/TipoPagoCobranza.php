<?php

namespace App\Enums;

enum TipoPagoCobranza: string
{
    use ConEtiqueta;

    case Completo = 'Completo';
    case Parcial = 'Parcial';
    case Anticipo = 'Anticipo';

    public function tono(): string
    {
        return match ($this) {
            self::Completo => 'verde',
            self::Parcial => 'ambar',
            self::Anticipo => 'azul',
        };
    }
}
