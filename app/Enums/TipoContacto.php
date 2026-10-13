<?php

namespace App\Enums;

enum TipoContacto: string
{
    use ConEtiqueta;

    case Visita = 'Visita';
    case Llamada = 'Llamada';

    public function icono(): string
    {
        return $this === self::Visita ? 'ubicacion' : 'telefono';
    }
}
