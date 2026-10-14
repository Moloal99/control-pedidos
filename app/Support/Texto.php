<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Texto
{
    /**
     * Operador para buscar sin distinguir mayusculas: PostgreSQL (Render)
     * distingue con LIKE; MariaDB y SQLite no.
     */
    public static function like(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /** «Ferretería Don Bosco» → «ferreteria don bosco» (para buscar sin acentos). */
    public static function plano(?string $texto): string
    {
        return mb_strtolower(trim(Str::ascii((string) $texto)));
    }
}
