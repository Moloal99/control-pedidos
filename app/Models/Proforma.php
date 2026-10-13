<?php

namespace App\Models;

use App\Enums\EstadoProforma;
use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una proforma por almacen del pedido (fact_PROFORMAS). */
class Proforma extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'pedido_id', 'pedido_numero', 'clave', 'almacen_codigo', 'almacen',
        'numero', 'fecha', 'total', 'total_despachado', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'total' => 'decimal:2',
            'total_despachado' => 'decimal:2',
            'estado' => EstadoProforma::class,
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_PROFORMAS',
            'campos' => [
                'clave' => 'PROFORMA_CLAVE',
                'pedido_numero' => 'PEDIDO_NUMERO',
                'almacen' => 'PROFORMA_ALMACEN',
                'numero' => 'PROFORMA_NUMERO',
                'fecha' => 'PROFORMA_FECHA',
                'total' => 'PROFORMA_TOTAL',
                'total_despachado' => 'PROFORMA_TOTAL_DESPACHADO',
                'estado' => 'PROFORMA_ESTADO',
            ],
            // Columnas que SharePoint guarda copiadas del pedido
            'copias' => [
                'PROFORMA_TITULO' => 'clave',
                'CLIENTE_CODIGO' => 'pedido.cliente_codigo',
                'CLIENTE_NOMBRE' => 'pedido.cliente_nombre',
                'VENDEDOR_CODIGO' => 'pedido.vendedor_codigo',
                'VENDEDOR_NOMBRE' => 'pedido.vendedor_nombre',
                '@lookup:dim_CLIENTES_ID' => 'pedido.cliente.sp_id',
            ],
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class)->orderBy('id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaVenta::class)->orderBy('fecha');
    }

    public function notasActivas(): HasMany
    {
        return $this->notas()->where('anulada', false);
    }

    public function tieneNumero(): bool
    {
        return trim((string) $this->numero) !== '';
    }

    /** Bloqueada para edicion: ya tiene notas de venta activas. */
    public function bloqueada(): bool
    {
        return $this->notasActivas()->exists();
    }

    public function almacenModelo(): ?Almacen
    {
        return Almacen::query()->where('codigo', $this->almacen_codigo)->first();
    }
}
