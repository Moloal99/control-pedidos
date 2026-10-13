<?php

namespace App\Models;

use App\Enums\AccionBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento de bitacora: una cosa que paso, con quien la hizo y sobre que.
 */
class Bitacora extends Model
{
    protected $table = 'bitacora';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sesion_id', 'user_id', 'usuario_nombre', 'accion', 'entidad',
        'entidad_id', 'referencia', 'descripcion', 'datos', 'ruta', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'accion' => AccionBitacora::class,
            'datos' => 'array',
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
}
