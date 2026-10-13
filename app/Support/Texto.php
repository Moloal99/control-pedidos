<?php

namespace App\Support;

use Illuminate\Support\Str;

class Texto
{
    /** «Ferretería Don Bosco» → «ferreteria don bosco» (para buscar sin acentos). */
    public static function plano(?string $texto): string
    {
        return mb_strtolower(trim(Str::ascii((string) $texto)));
    }
}
