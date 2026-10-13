<?php

namespace App\Servicios;

use Illuminate\Validation\ValidationException;

/**
 * Regla de negocio incumplida. Se muestra al usuario tal cual (los textos
 * vienen de la Power App, pasados a usted).
 */
class Falla
{
    public static function con(string $mensaje, string $campo = 'general'): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }

    public static function si(bool $condicion, string $mensaje, string $campo = 'general'): void
    {
        if ($condicion) {
            self::con($mensaje, $campo);
        }
    }
}
