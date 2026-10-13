<?php

namespace App\Models;

use App\SharePoint\EnEspejo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Producto despachado en una nota (fact_NOTA_LINEAS). */
class NotaLinea extends Model
{
    use EnEspejo, HasFactory;

    protected $table = 'nota_lineas';

    protected $fillable = [
        'sp_id', 'nota_id', 'pedido_linea_id', 'nota_numero', 'proforma_clave', 'producto_codigo',
        'producto_descripcion', 'producto_unidad', 'almacen', 'cantidad', 'precio_unitario',
        'producto_peso_promedio',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'producto_peso_promedio' => 'decimal:4',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'fact_NOTA_LINEAS',
            'campos' => [
                'nota_numero' => 'NOTA_NUMERO',
                'proforma_clave' => 'PROFORMA_CLAVE',
                'producto_codigo' => 'PRODUCTO_TERMINADO_CODIGO',
                'producto_descripcion' => 'PRODUCTO_DESCRIPCION',
                'producto_unidad' => 'PRODUCTO_UNIDAD',
                'almacen' => 'LINEA_ALMACEN',
                'cantidad' => 'LINEA_CANTIDAD',
                'precio_unitario' => 'LINEA_PRECIO_UNITARIO',
                'producto_peso_promedio' => 'PRODUCTO_PESO_PROMEDIO',
            ],
            'copias' => [
                'Title' => 'nota_numero',
            ],
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaVenta::class, 'nota_id');
    }

    public function pedidoLinea(): BelongsTo
    {
        return $this->belongsTo(PedidoLinea::class);
    }

    public function subtotal(): float
    {
        return round((float) $this->cantidad * (float) $this->precio_unitario, 2);
    }
}
