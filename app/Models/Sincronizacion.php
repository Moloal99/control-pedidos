<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Estado de la sincronizacion de una lista de SharePoint. */
class Sincronizacion extends Model
{
    protected $table = 'sincronizaciones';

    protected $fillable = ['lista', 'ultima_at', 'marca_at', 'filas', 'estado', 'error'];

    protected function casts(): array
    {
        return [
            'ultima_at' => 'datetime',
            'marca_at' => 'datetime',
        ];
    }
}
