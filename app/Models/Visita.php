<?php

namespace App\Models;

use App\Enums\FormaPago;
use App\Enums\MotivoVisita;
use App\Enums\TipoContacto;
use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Visita o llamada a un cliente (fact_VISITAS). */
class Visita extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'titulo', 'cliente_id', 'cliente_codigo', 'cliente_nombre',
        'vendedor_id', 'vendedor_codigo', 'vendedor_nombre', 'cargado_por_id', 'cargado_por',
        'motivo', 'tipo_contacto', 'forma_pago', 'dias_credito', 'comentario', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'motivo' => MotivoVisita::class,
            'tipo_contacto' => TipoContacto::class,
            'forma_pago' => FormaPago::class,
            'dias_credito' => 'integer',
            'fecha' => 'datetime',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_VISITAS',
            'campos' => [
                'titulo' => 'VISITA_TITULO',
                'cliente_codigo' => 'CLIENTE_CODIGO',
                'cliente_nombre' => 'CLIENTE_NOMBRE',
                'vendedor_codigo' => 'VENDEDOR_CODIGO',
                'vendedor_nombre' => 'VENDEDOR_NOMBRE',
                'cargado_por' => 'CARGADO_POR',
                'motivo' => 'VISITA_MOTIVO',
                'tipo_contacto' => 'VISITA_TIPO_CONTACTO',
                'forma_pago' => 'VISITA_FORMA_PAGO',
                'dias_credito' => 'VISITA_DIAS_CREDITO',
                'comentario' => 'VISITA_COMENTARIO',
                'fecha' => 'VISITA_FECHA',
            ],
            'lookups' => [
                'cliente_id' => ['dim_CLIENTES_ID', Cliente::class],
                'vendedor_id' => ['dim_VENDEDORES_ID', User::class],
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

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function esPedido(): bool
    {
        return $this->motivo === MotivoVisita::Pedido;
    }
}
