<?php

namespace App\Models;

use App\SharePoint\EnEspejo;
use App\Support\Almacenes;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Espejo de dim_PRODUCTOS (solo lectura desde la app). */
class Producto extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = [
        'sp_id', 'codigo', 'descripcion', 'unidad', 'micronaje', 'peso_promedio',
        'bolsas_por_paquete', 'paquetes_por_jaba', 'jabitas_por_jaba', 'jabas_por_bulto',
        'peso_por_bolsa', 'peso_por_bulto', 'peso_por_paquete', 'peso_por_jaba',
    ];

    protected function casts(): array
    {
        return [
            'peso_promedio' => 'decimal:4',
            'bolsas_por_paquete' => 'decimal:2',
            'paquetes_por_jaba' => 'decimal:2',
            'jabitas_por_jaba' => 'decimal:2',
            'jabas_por_bulto' => 'decimal:2',
            'peso_por_bolsa' => 'decimal:4',
            'peso_por_bulto' => 'decimal:4',
            'peso_por_paquete' => 'decimal:4',
            'peso_por_jaba' => 'decimal:4',
        ];
    }

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'dim_PRODUCTOS',
            'soloLectura' => true,
            'campos' => [
                'codigo' => 'PRODUCTO_TERMINADO_CODIGO',
                'descripcion' => 'PRODUCTO_DESCRIPCION',
                'unidad' => 'PRODUCTO_UNIDAD',
                'micronaje' => 'PRODUCTO_MICRONAJE',
                'peso_promedio' => 'PRODUCTO_PESO_PROMEDIO',
                'bolsas_por_paquete' => 'PRODUCTO_BOLSAS_POR_PAQUETE',
                'paquetes_por_jaba' => 'PRODUCTO_PAQUETES_POR_JABA',
                'jabitas_por_jaba' => 'PRODUCTO_JABITAS_POR_JABA',
                'jabas_por_bulto' => 'PRODUCTO_JABAS_POR_BULTO',
                'peso_por_bolsa' => 'PRODUCTO_PESO_POR_BOLSA',
                'peso_por_bulto' => 'PRODUCTO_PESO_POR_BULTO',
                'peso_por_paquete' => 'PRODUCTO_PESO_POR_PAQUETE',
                'peso_por_jaba' => 'PRODUCTO_PESO_POR_JABA',
            ],
        ];
    }

    public function scopeBuscar(Builder $consulta, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $consulta;
        }

        foreach (preg_split('/\s+/', Texto::plano($texto)) ?: [] as $palabra) {
            $consulta->where('busqueda', Texto::like(), "%{$palabra}%");
        }

        return $consulta;
    }

    protected static function booted(): void
    {
        static::saving(fn (Producto $p) => $p->busqueda = Texto::plano($p->descripcion.' '.$p->codigo));
    }

    public function unidadNormalizada(): string
    {
        return mb_strtoupper(trim((string) $this->unidad));
    }

    /** Las piezas se venden enteras. */
    public function esPorPieza(): bool
    {
        return $this->unidadNormalizada() === 'PZA';
    }

    public function almacen(): Almacen
    {
        return Almacenes::paraProducto($this->codigo);
    }
}
