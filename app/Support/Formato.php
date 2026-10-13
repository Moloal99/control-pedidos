<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Formatos de pantalla: bolivianos, cantidades y fechas relativas. */
class Formato
{
    public static function bs(float|int|string|null $valor, int $decimales = 2): string
    {
        return 'Bs '.number_format((float) $valor, $decimales, '.', ',');
    }

    /** 12 · 12.5 · 1,250 (sin ceros de mas). */
    public static function cantidad(float|int|string|null $valor): string
    {
        $texto = number_format((float) $valor, 3, '.', ',');

        return rtrim(rtrim($texto, '0'), '.');
    }

    public static function kg(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, '.', ',').' kg';
    }

    public static function compacto(float|int|string|null $valor): string
    {
        $v = (float) $valor;

        return match (true) {
            abs($v) >= 1_000_000 => 'Bs '.number_format($v / 1_000_000, 1).' M',
            abs($v) >= 10_000 => 'Bs '.number_format($v / 1_000, 1).' mil',
            default => self::bs($v),
        };
    }

    public static function fecha(?CarbonInterface $fecha, bool $conHora = false): string
    {
        if (! $fecha) {
            return '—';
        }

        return $fecha->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }

    /** «hace 5 min», «ayer 14:30», «12/09 08:15». */
    public static function relativa(?CarbonInterface $fecha): string
    {
        if (! $fecha) {
            return '—';
        }

        return match (true) {
            $fecha->gt(now()->subMinute()) => 'recién',
            $fecha->gt(now()->subHour()) => 'hace '.(int) $fecha->diffInMinutes(now()).' min',
            $fecha->isToday() => 'hoy '.$fecha->format('H:i'),
            $fecha->isYesterday() => 'ayer '.$fecha->format('H:i'),
            $fecha->gt(now()->subDays(6)) => $fecha->translatedFormat('l H:i'),
            default => $fecha->format('d/m/Y H:i'),
        };
    }

    public static function saludo(): string
    {
        $hora = (int) now()->format('G');

        return match (true) {
            $hora < 12 => 'Buenos días',
            $hora < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }
}
