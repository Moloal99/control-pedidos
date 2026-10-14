<?php

namespace App\Models;

use App\Enums\EstadoCobranza;
use App\Enums\FormaPagoCobranza;
use App\Enums\TipoPagoCobranza;
use App\SharePoint\EnEspejo;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gestion de cobro sobre una nota de venta (fact_COBRANZAS). */
class Cobranza extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'nota_id', 'nota_numero', 'proforma_clave', 'pedido_numero', 'cliente_id',
        'cliente_codigo', 'cliente_nombre', 'vendedor_codigo', 'vendedor_nombre', 'cargado_por_id',
        'cargado_por', 'fecha', 'hubo_pago', 'tipo_pago', 'monto', 'forma_pago', 'fecha_proximo_pago',
        'nota_total', 'saldo_antes', 'saldo_despues', 'estado', 'numero', 'confirmada_por',
        'fecha_confirmacion', 'numero_anterior', 'numero_motivo', 'numero_modif_por',
        'fecha_modif_numero', 'comprobante_nombre', 'comprobante_ruta',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'fecha_proximo_pago' => 'date',
            'fecha_confirmacion' => 'datetime',
            'fecha_modif_numero' => 'datetime',
            'hubo_pago' => 'boolean',
            'tipo_pago' => TipoPagoCobranza::class,
            'forma_pago' => FormaPagoCobranza::class,
            'estado' => EstadoCobranza::class,
            'monto' => 'decimal:2',
            'nota_total' => 'decimal:2',
            'saldo_antes' => 'decimal:2',
            'saldo_despues' => 'decimal:2',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_COBRANZAS',
            'campos' => [
                'nota_numero' => 'NOTA_NUMERO',
                'proforma_clave' => 'PROFORMA_CLAVE',
                'pedido_numero' => 'PEDIDO_NUMERO',
                'cliente_codigo' => 'CLIENTE_CODIGO',
                'cliente_nombre' => 'CLIENTE_NOMBRE',
                'vendedor_codigo' => 'VENDEDOR_CODIGO',
                'vendedor_nombre' => 'VENDEDOR_NOMBRE',
                'cargado_por' => 'CARGADO_POR',
                'fecha' => 'COBRANZA_FECHA',
                'hubo_pago' => 'COBRANZA_HUBO_PAGO',
                'tipo_pago' => 'COBRANZA_TIPO_PAGO',
                'monto' => 'COBRANZA_MONTO',
                'forma_pago' => 'COBRANZA_FORMA_PAGO',
                'fecha_proximo_pago' => 'COBRANZA_FECHA_PROXIMO_PAGO',
                'nota_total' => 'NOTA_TOTAL',
                'saldo_antes' => 'COBRANZA_SALDO_ANTES',
                'saldo_despues' => 'COBRANZA_SALDO_DESPUES',
                'estado' => 'COBRANZA_ESTADO',
                'numero' => 'COBRANZA_NUMERO',
                'confirmada_por' => 'COBRANZA_CONFIRMADA_POR',
                'fecha_confirmacion' => 'COBRANZA_FECHA_CONFIRMACION',
                'numero_anterior' => 'COBRANZA_NUMERO_ANTERIOR',
                'numero_motivo' => 'COBRANZA_NUMERO_MOTIVO',
                'numero_modif_por' => 'COBRANZA_NUMERO_MODIF_POR',
                'fecha_modif_numero' => 'COBRANZA_FECHA_MODIF_NUMERO',
            ],
            'fechas' => ['fecha_proximo_pago'],
            'lookups' => [
                'cliente_id' => ['dim_CLIENTES_ID', Cliente::class],
            ],
            'copias' => [
                'COBRANZA_TITULO' => 'nota_numero',
            ],
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === EstadoCobranza::Confirmada;
    }

    public function scopeVisiblesPara(Builder $consulta, User $usuario): Builder
    {
        if ($usuario->veTodasLasCobranzas()) {
            return $consulta;
        }

        return $consulta->where(fn ($q) => $q->where('vendedor_codigo', $usuario->codigo)->orWhere('cargado_por_id', $usuario->id));
    }

    public function scopeBuscar(Builder $consulta, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $consulta;
        }

        return $consulta->where(fn ($q) => $q
            ->where('nota_numero', Texto::like(), "%{$texto}%")
            ->orWhere('cliente_nombre', Texto::like(), "%{$texto}%")
            ->orWhere('vendedor_nombre', Texto::like(), "%{$texto}%")
            ->orWhere('numero', Texto::like(), "%{$texto}%"));
    }
}
