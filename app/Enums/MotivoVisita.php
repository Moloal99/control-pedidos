<?php

namespace App\Enums;

enum MotivoVisita: string
{
    use ConEtiqueta;

    case Rutina = 'Rutina';
    case Cobranza = 'Cobranza';
    case Pedido = 'Pedido';

    public function icono(): string
    {
        return match ($this) {
            self::Rutina => 'ruta',
            self::Cobranza => 'billete',
            self::Pedido => 'carrito',
        };
    }
}
