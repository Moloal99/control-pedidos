<?php

namespace App\Models;

use App\SharePoint\EnEspejo;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Espejo de dim_CLIENTES (solo lectura desde la app). */
class Cliente extends Model
{
    use EnEspejo, HasFactory;

    protected $fillable = ['sp_id', 'codigo', 'nombre', 'direccion', 'telefono'];

    public static function mapaSharePoint(): array
    {
        return [
            'lista' => 'dim_CLIENTES',
            'soloLectura' => true,
            'campos' => [
                'codigo' => 'CLIENTE_CODIGO',
                'nombre' => 'CLIENTE_NOMBRE',
                'direccion' => 'CLIENTE_DIRECCION',
                'telefono' => 'CLIENTE_TELEFONO',
            ],
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Cliente $c) => $c->busqueda = Texto::plano($c->nombre.' '.$c->codigo));
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /** Busqueda por «contiene» en nombre o codigo (la Power App solo buscaba por prefijo). */
    public function scopeBuscar(Builder $consulta, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $consulta;
        }

        // Cada palabra debe aparecer, en cualquier orden y sin importar acentos
        foreach (preg_split('/\s+/', Texto::plano($texto)) ?: [] as $palabra) {
            $consulta->where('busqueda', Texto::like(), "%{$palabra}%");
        }

        return $consulta;
    }

    /** En SharePoint «0» significa vacio. */
    public function direccionLegible(): ?string
    {
        $valor = trim((string) $this->direccion);

        return $valor === '' || $valor === '0' ? null : $valor;
    }

    public function telefonoLegible(): ?string
    {
        $valor = trim((string) $this->telefono);

        return $valor === '' || $valor === '0' ? null : $valor;
    }
}
