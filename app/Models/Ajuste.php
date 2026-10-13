<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Ajustes editables (correos por rol, copia externa, ventana de dias, ...).
 * Si una clave no esta en la base se usa el valor de config/pedidos.php.
 */
class Ajuste extends Model
{
    protected $primaryKey = 'clave';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['clave', 'valor'];

    public static function valor(string $clave, mixed $omision = null): mixed
    {
        $guardados = Cache::rememberForever('ajustes', fn () => static::query()->pluck('valor', 'clave')->all());

        return $guardados[$clave] ?? config('pedidos.ajustes.'.$clave, $omision);
    }

    public static function fijar(string $clave, ?string $valor): void
    {
        static::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget('ajustes');
    }

    /**
     * @return list<string>
     */
    public static function correos(string $clave): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($c) => mb_strtolower(trim($c)),
            preg_split('/[;,\s]+/', (string) static::valor($clave, '')) ?: [],
        ))));
    }
}
