<?php

namespace App\Enums;

/** PEDIDO_ESTADO_DESPACHO. */
enum EstadoDespacho: string
{
    use ConEtiqueta;

    case SinProforma = 'Sin proforma';
    case Proformado = 'Proformado';
    case Parcial = 'Despachado parcial';
    case Total = 'Despachado total';

    public function corto(): string
    {
        return match ($this) {
            self::SinProforma => 'Sin proforma',
            self::Proformado => 'Proformado',
            self::Parcial => 'Parcial',
            self::Total => 'Despachado',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::SinProforma => 'ambar',
            self::Proformado => 'azul',
            self::Parcial => 'naranja',
            self::Total => 'verde',
        };
    }

    /** Posicion en el recorrido del pedido (para la barra de progreso). */
    public function paso(): int
    {
        return match ($this) {
            self::SinProforma => 1,
            self::Proformado => 2,
            self::Parcial => 3,
            self::Total => 4,
        };
    }
}
