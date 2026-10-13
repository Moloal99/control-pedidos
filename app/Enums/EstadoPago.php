<?php

namespace App\Enums;

/** Estado de pago del pedido y de la nota de venta. */
enum EstadoPago: string
{
    use ConEtiqueta;

    case Pendiente = 'Pendiente';
    case Parcial = 'Parcial';
    case Pagado = 'Pagado';

    public function tono(): string
    {
        return match ($this) {
            self::Pagado => 'verde',
            self::Parcial => 'ambar',
            self::Pendiente => 'rojo',
        };
    }

    public static function segun(float $total, float $pagado): self
    {
        if ($total > 0 && $total - $pagado <= 0.004) {
            return self::Pagado;
        }

        return $pagado > 0 ? self::Parcial : self::Pendiente;
    }
}
