<?php

namespace App\Models;

use App\Enums\EstadoPago;
use App\Enums\FormaPago;
use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Nota de venta = despacho de una proforma (fact_NOTAS_VENTA). */
class NotaVenta extends Model
{
    use EnEspejo, HasFactory;

    protected $table = 'notas_venta';

    protected $fillable = [
        'sp_id', 'numero', 'proforma_id', 'pedido_id', 'proforma_clave', 'proforma_numero', 'pedido_numero',
        'cliente_id', 'cliente_codigo', 'cliente_nombre', 'vendedor_codigo', 'vendedor_nombre',
        'almacen', 'fecha', 'total', 'total_pagado', 'saldo_pendiente', 'estado_pago',
        'forma_pago', 'dias_credito', 'fecha_vencimiento', 'anulada', 'anulada_motivo',
        'anulada_por', 'anulada_fecha', 'comprobante_nombre', 'comprobante_ruta', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'anulada_fecha' => 'datetime',
            'total' => 'decimal:2',
            'total_pagado' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
            'estado_pago' => EstadoPago::class,
            'forma_pago' => FormaPago::class,
            'dias_credito' => 'integer',
            'anulada' => 'boolean',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_NOTAS_VENTA',
            'campos' => [
                'numero' => 'NOTA_NUMERO',
                'proforma_clave' => 'PROFORMA_CLAVE',
                'proforma_numero' => 'PROFORMA_NUMERO',
                'pedido_numero' => 'PEDIDO_NUMERO',
                'cliente_codigo' => 'CLIENTE_CODIGO',
                'cliente_nombre' => 'CLIENTE_NOMBRE',
                'vendedor_codigo' => 'VENDEDOR_CODIGO',
                'vendedor_nombre' => 'VENDEDOR_NOMBRE',
                'almacen' => 'NOTA_ALMACEN',
                'fecha' => 'NOTA_FECHA',
                'total' => 'NOTA_TOTAL',
                'total_pagado' => 'NOTA_TOTAL_PAGADO',
                'saldo_pendiente' => 'NOTA_SALDO_PENDIENTE',
                'estado_pago' => 'NOTA_ESTADO_PAGO',
                'forma_pago' => 'NOTA_FORMA_PAGO',
                'dias_credito' => 'NOTA_DIAS_CREDITO',
                'fecha_vencimiento' => 'NOTA_FECHA_VENCIMIENTO',
                'anulada' => 'NOTA_ANULADA',
                'anulada_motivo' => 'NOTA_ANULADA_MOTIVO',
                'anulada_por' => 'NOTA_ANULADA_POR',
                'anulada_fecha' => 'NOTA_ANULADA_FECHA',
            ],
            'lookups' => [
                'cliente_id' => ['dim_CLIENTES_ID', Cliente::class],
            ],
            'copias' => [
                'Title' => 'numero',
            ],
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(NotaLinea::class, 'nota_id')->orderBy('id');
    }

    public function cobranzas(): HasMany
    {
        return $this->hasMany(Cobranza::class, 'nota_id')->orderByDesc('fecha');
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('anulada', false);
    }

    /** Notas con saldo que se pueden cobrar (la Power App: saldo > 0 y no anulada). */
    public function scopeCobrables(Builder $consulta, User $usuario): Builder
    {
        return $consulta->activas()
            ->where('saldo_pendiente', '>', 0)
            ->when(! $usuario->veTodasLasNotas(), fn ($q) => $q->where('vendedor_codigo', $usuario->codigo));
    }

    /** Monto de cobranzas con pago aun sin confirmar. */
    public function montoPorConfirmar(): float
    {
        return (float) Cobranza::query()
            ->where('nota_id', $this->getKey())
            ->where('hubo_pago', true)
            ->where('estado', \App\Enums\EstadoCobranza::Pendiente->value)
            ->sum('monto');
    }

    /** Lo que todavia se puede cobrar: saldo menos lo que espera aprobacion. */
    public function disponibleParaCobrar(): float
    {
        return max(0, round((float) $this->saldo_pendiente - $this->montoPorConfirmar(), 2));
    }

    public function estaVencida(): bool
    {
        return ! $this->anulada && (float) $this->saldo_pendiente > 0
            && $this->fecha_vencimiento !== null && $this->fecha_vencimiento->isPast();
    }
}
