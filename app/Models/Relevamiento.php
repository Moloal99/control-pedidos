<?php

namespace App\Models;

use App\Enums\TipoCompetencia;
use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Relevamiento de precios de la competencia (fact_COMPETENCIA). */
class Relevamiento extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'titulo', 'fecha', 'vendedor_id', 'vendedor_codigo', 'vendedor_nombre', 'cargado_por_id',
        'cliente_id', 'cliente_codigo', 'cliente_nombre', 'competidor', 'tipo', 'descripcion',
        'medida', 'unidad', 'precio', 'cantidad', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'tipo' => TipoCompetencia::class,
            'precio' => 'decimal:2',
            'cantidad' => 'decimal:3',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_COMPETENCIA',
            'campos' => [
                'titulo' => 'Title',
                'fecha' => 'RELEVAMIENTO_FECHA',
                'vendedor_codigo' => 'VENDEDOR_CODIGO',
                'vendedor_nombre' => 'VENDEDOR_NOMBRE',
                'cliente_codigo' => 'CLIENTE_CODIGO',
                'cliente_nombre' => 'CLIENTE_NOMBRE',
                'competidor' => 'COMPETIDOR',
                'tipo' => 'COMP_TIPO',
                'descripcion' => 'COMP_DESCRIPCION',
                'medida' => 'COMP_MEDIDA',
                'unidad' => 'COMP_UNIDAD',
                'precio' => 'COMP_PRECIO',
                'cantidad' => 'COMP_CANTIDAD',
                'observacion' => 'COMP_OBSERVACION',
            ],
            'lookups' => [
                'cliente_id' => ['dim_CLIENTES_ID', Cliente::class],
            ],
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }
}
