<?php

namespace App\Support;

use App\Models\Almacen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolucion de almacen por prefijo del codigo de producto. Reemplaza la
 * cadena de If(StartsWith(...)) de la Power App por una tabla editable.
 */
class Almacenes
{
    /**
     * @return Collection<int, Almacen>
     */
    public static function todos(): Collection
    {
        // Se cachean arreglos: Laravel 13 no deserializa modelos desde la cache
        $filas = Cache::rememberForever('almacenes', fn () => Almacen::query()->orderBy('orden')->get()->map->getAttributes()->all());

        return collect($filas)->map(fn (array $a) => (new Almacen)->newFromBuilder($a));
    }

    public static function olvidar(): void
    {
        Cache::forget('almacenes');
    }

    public static function paraProducto(?string $codigo): Almacen
    {
        $codigo = mb_strtoupper(trim((string) $codigo));

        foreach (self::todos() as $almacen) {
            foreach ($almacen->listaPrefijos() as $prefijo) {
                if ($prefijo !== '' && str_starts_with($codigo, mb_strtoupper($prefijo))) {
                    return $almacen;
                }
            }
        }

        return self::sinAsignar();
    }

    public static function porCodigo(?string $codigo): Almacen
    {
        return self::todos()->firstWhere('codigo', $codigo) ?? self::sinAsignar();
    }

    /** Proforma «-SIN» para productos sin prefijo conocido (igual que la Power App). */
    public static function sinAsignar(): Almacen
    {
        return self::todos()->firstWhere('codigo', Almacen::SIN)
            ?? new Almacen(['codigo' => Almacen::SIN, 'nombre' => 'SIN ALMACÉN ASIGNADO', 'area' => 'Sin asignar', 'color' => '#6a7592']);
    }
}
