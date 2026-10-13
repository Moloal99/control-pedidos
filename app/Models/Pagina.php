<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso del usuario por una pantalla: cuando entro, cuando la dejo y cuantos
 * segundos estuvo. La cierra la pantalla siguiente o el latido del navegador.
 * (En el portal central se llama «Visita»; aqui ese nombre es del negocio.)
 */
class Pagina extends Model
{
    protected $table = 'traza_paginas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sesion_id', 'user_id', 'ruta', 'url', 'titulo',
        'entrada_at', 'salida_at', 'segundos',
    ];

    protected function casts(): array
    {
        return [
            'entrada_at' => 'datetime',
            'salida_at' => 'datetime',
        ];
    }

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permanenciaLegible(): string
    {
        return Sesion::formatear($this->segundos);
    }
}
