<?php

namespace App\Models;

use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Linea de pedido con la «foto» del producto (fact_PEDIDO_LINEAS). */
class PedidoLinea extends Model
{
    use EnEspejo, HasFactory;

    protected $table = 'pedido_lineas';

    protected $fillable = [
        'sp_id', 'pedido_id', 'proforma_id', 'producto_id', 'pedido_numero', 'proforma_clave', 'almacen',
        'producto_codigo', 'producto_descripcion', 'producto_unidad', 'producto_micronaje',
        'producto_peso_promedio', 'producto_bolsas_por_paquete', 'producto_paquetes_por_jaba',
        'producto_jabitas_por_jaba', 'producto_jabas_por_bulto', 'producto_peso_por_bolsa',
        'producto_peso_por_bulto', 'producto_peso_por_paquete', 'producto_peso_por_jaba',
        'cantidad', 'precio_unitario', 'cantidad_despachada',
    ];

    protected function casts(): array
    {
        return [
            'producto_peso_promedio' => 'decimal:4',
            'producto_bolsas_por_paquete' => 'decimal:2',
            'producto_paquetes_por_jaba' => 'decimal:2',
            'producto_jabitas_por_jaba' => 'decimal:2',
            'producto_jabas_por_bulto' => 'decimal:2',
            'producto_peso_por_bolsa' => 'decimal:4',
            'producto_peso_por_bulto' => 'decimal:4',
            'producto_peso_por_paquete' => 'decimal:4',
            'producto_peso_por_jaba' => 'decimal:4',
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'cantidad_despachada' => 'decimal:3',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_PEDIDO_LINEAS',
            'campos' => [
                'pedido_numero' => 'PEDIDO_NUMERO',
                'proforma_clave' => 'PROFORMA_CLAVE',
                'almacen' => 'LINEA_ALMACEN',
                'producto_codigo' => 'PRODUCTO_TERMINADO_CODIGO',
                'producto_descripcion' => 'PRODUCTO_DESCRIPCION',
                'producto_unidad' => 'PRODUCTO_UNIDAD',
                'producto_micronaje' => 'PRODUCTO_MICRONAJE',
                'producto_peso_promedio' => 'PRODUCTO_PESO_PROMEDIO',
                'producto_bolsas_por_paquete' => 'PRODUCTO_BOLSAS_POR_PAQUETE',
                'producto_paquetes_por_jaba' => 'PRODUCTO_PAQUETES_POR_JABA',
                'producto_jabitas_por_jaba' => 'PRODUCTO_JABITAS_POR_JABA',
                'producto_jabas_por_bulto' => 'PRODUCTO_JABAS_POR_BULTO',
                'producto_peso_por_bolsa' => 'PRODUCTO_PESO_POR_BOLSA',
                'producto_peso_por_bulto' => 'PRODUCTO_PESO_POR_BULTO',
                'producto_peso_por_paquete' => 'PRODUCTO_PESO_POR_PAQUETE',
                'producto_peso_por_jaba' => 'PRODUCTO_PESO_POR_JABA',
                'cantidad' => 'LINEA_CANTIDAD',
                'precio_unitario' => 'LINEA_PRECIO_UNITARIO',
                'cantidad_despachada' => 'LINEA_CANTIDAD_DESPACHADA',
            ],
            'lookups' => [
                'producto_id' => ['dim_PRODUCTOS_ID', Producto::class],
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

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function notaLineas(): HasMany
    {
        return $this->hasMany(NotaLinea::class);
    }

    public function subtotal(): float
    {
        return round((float) $this->cantidad * (float) $this->precio_unitario, 2);
    }

    public function peso(): float
    {
        return (float) $this->cantidad * (float) $this->producto_peso_promedio;
    }

    public function esPorPieza(): bool
    {
        return mb_strtoupper(trim((string) $this->producto_unidad)) === 'PZA';
    }
}
