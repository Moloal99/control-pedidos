<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada aviso por correo: en cola, enviado o fallido. */
class Correo extends Model
{
    protected $fillable = [
        'evento', 'asunto', 'destinatarios', 'referencia', 'estado',
        'intentos', 'error', 'carga', 'user_id', 'enviado_at',
    ];

    protected $hidden = ['carga'];

    protected function casts(): array
    {
        return ['enviado_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
