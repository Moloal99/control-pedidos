<?php

namespace App\Enums;

enum EstadoCobranza: string
{
    use ConEtiqueta;

    case Pendiente = 'Pendiente de confirmación';
    case Confirmada = 'Confirmada';

    public function corto(): string
    {
        return $this === self::Confirmada ? 'Confirmada' : 'Esperando aprobación';
    }

    public function tono(): string
    {
        return $this === self::Confirmada ? 'verde' : 'ambar';
    }
}
