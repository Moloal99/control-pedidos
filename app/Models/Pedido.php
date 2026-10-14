<?php

namespace App\Models;

use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\FormaPago;
use App\Enums\TipoContacto;
use App\SharePoint\EnEspejo;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cabecera de pedido (fact_PEDIDOS). Se le llama «ticket» en Detalles. */
class Pedido extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'numero', 'visita_id', 'cliente_id', 'cliente_codigo', 'cliente_nombre',
        'vendedor_id', 'vendedor_codigo', 'vendedor_nombre', 'cargado_por_id', 'cargado_por',
        'tipo_contacto', 'forma_pago', 'dias_credito', 'fecha', 'fecha_vencimiento',
        'total', 'total_pagado', 'saldo_pendiente', 'estado_pago', 'estado_despacho',
        'modificado_por', 'fecha_modificacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo_contacto' => TipoContacto::class,
            'forma_pago' => FormaPago::class,
            'dias_credito' => 'integer',
            'fecha' => 'datetime',
            'fecha_vencimiento' => 'datetime',
            'fecha_modificacion' => 'datetime',
            'total' => 'decimal:2',
            'total_pagado' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
            'estado_pago' => EstadoPago::class,
            'estado_despacho' => EstadoDespacho::class,
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_PEDIDOS',
            'campos' => [
                'numero' => 'PEDIDO_NUMERO',
                'cliente_codigo' => 'CLIENTE_CODIGO',
                'cliente_nombre' => 'CLIENTE_NOMBRE',
                'vendedor_codigo' => 'VENDEDOR_CODIGO',
                'vendedor_nombre' => 'VENDEDOR_NOMBRE',
                'cargado_por' => 'CARGADO_POR',
                'tipo_contacto' => 'PEDIDO_TIPO_CONTACTO',
                'forma_pago' => 'PEDIDO_FORMA_PAGO',
                'dias_credito' => 'PEDIDO_DIAS_CREDITO',
                'fecha' => 'PEDIDO_FECHA',
                'fecha_vencimiento' => 'PEDIDO_FECHA_VENCIMIENTO',
                'total' => 'PEDIDO_TOTAL',
                'total_pagado' => 'PEDIDO_TOTAL_PAGADO',
                'saldo_pendiente' => 'PEDIDO_SALDO_PENDIENTE',
                'estado_pago' => 'PEDIDO_ESTADO_PAGO',
                'estado_despacho' => 'PEDIDO_ESTADO_DESPACHO',
                'modificado_por' => 'PEDIDO_MODIFICADO_POR',
                'fecha_modificacion' => 'PEDIDO_FECHA_MODIFICACION',
            ],
            'lookups' => [
                'visita_id' => ['fact_VISITAS_ID', Visita::class],
                'cliente_id' => ['dim_CLIENTES_ID', Cliente::class],
                'vendedor_id' => ['dim_VENDEDORES_ID', User::class],
            ],
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'numero';
    }

    public function visita(): BelongsTo
    {
        return $this->belongsTo(Visita::class);
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

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class)->orderBy('id');
    }

    public function proformas(): HasMany
    {
        return $this->hasMany(Proforma::class)->orderBy('almacen_codigo');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaVenta::class)->orderBy('fecha');
    }

    /** Lo que ve cada usuario en «Pedidos»: todos o solo los suyos (y los que cargo). */
    public function scopeVisiblesPara(Builder $consulta, User $usuario): Builder
    {
        if ($usuario->veTodosLosPedidos()) {
            return $consulta;
        }

        return $consulta->where(function ($q) use ($usuario) {
            $q->where('vendedor_codigo', $usuario->codigo)
                ->orWhere('cargado_por', $usuario->name);
        });
    }

    public function scopeBuscar(Builder $consulta, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $consulta;
        }

        return $consulta->where(fn ($q) => $q
            ->where('cliente_nombre', Texto::like(), "%{$texto}%")
            ->orWhere('vendedor_nombre', Texto::like(), "%{$texto}%")
            ->orWhere('numero', Texto::like(), "%{$texto}%")
            ->orWhere('cliente_codigo', Texto::like(), "{$texto}%"));
    }

    public function fueModificado(): bool
    {
        return $this->fecha_modificacion !== null;
    }

    /** Numeros de proforma «P-1  ·  P-2» o null. */
    public function numerosProforma(): ?string
    {
        $numeros = $this->proformas->pluck('numero')->filter()->implode('  ·  ');

        return $numeros !== '' ? $numeros : null;
    }

    public function pesoTotal(): float
    {
        return (float) $this->lineas->sum(fn (PedidoLinea $l) => $l->peso());
    }
}
