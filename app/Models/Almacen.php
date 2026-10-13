<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Almacen de destino de una proforma. Se elige por el prefijo del codigo de
 * producto (5T201/5T202 → P11, ...). Antes vivia fijo en la Power App.
 */
class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = ['codigo', 'nombre', 'area', 'prefijos', 'correos_encargados', 'color', 'orden'];

    /** Codigo de la proforma «sin almacen» para productos sin prefijo conocido. */
    public const SIN = 'SIN';

    /**
     * @return list<string>
     */
    public function listaPrefijos(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->prefijos))));
    }

    /**
     * @return list<string>
     */
    public function listaCorreos(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($c) => mb_strtolower(trim($c)),
            preg_split('/[;,\s]+/', (string) $this->correos_encargados) ?: [],
        ))));
    }

    public function esSinAsignar(): bool
    {
        return $this->codigo === self::SIN;
    }
}
