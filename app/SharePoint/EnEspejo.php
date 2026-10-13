<?php

namespace App\SharePoint;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un modelo «en espejo» es la copia local de un elemento de una lista de
 * SharePoint. El modelo declara en `mapaSharePoint()`:
 *
 *  - lista:   nombre de la lista (dim_CLIENTES, fact_PEDIDOS, ...)
 *  - campos:  atributo local => nombre VISIBLE de la columna en SharePoint
 *             (el mismo que usa la Power App; el conector lo traduce al
 *             nombre interno consultando las columnas de la lista)
 *  - lookups: atributo FK local => [columna de busqueda, Modelo relacionado]
 *  - fechas:  atributos que viajan como fecha sin hora
 *
 * @mixin Model
 */
trait EnEspejo
{
    /**
     * @return array{lista:string, campos:array<string,string>, lookups?:array<string,array{0:string,1:class-string}>, fechas?:list<string>, soloLectura?:bool}
     */
    abstract public static function mapaSharePoint(): array;

    public static function listaSharePoint(): string
    {
        return static::mapaSharePoint()['lista'];
    }

    /**
     * Campos para escribir en SharePoint. Con $solo se limitan a esos
     * atributos (para no pisar columnas que otro usuario cambio).
     *
     * @param  list<string>|null  $solo
     * @return array<string, mixed>
     */
    public function paraSharePoint(?array $solo = null): array
    {
        $mapa = static::mapaSharePoint();
        $fechas = $mapa['fechas'] ?? [];
        $salida = [];

        foreach ($mapa['campos'] as $atributo => $columna) {
            if ($solo !== null && ! in_array($atributo, $solo, true)) {
                continue;
            }

            $valor = $this->getAttribute($atributo);
            $cast = $this->getCasts()[$atributo] ?? null;

            // Los decimales de Eloquent llegan como texto ("12.50"): SharePoint quiere numero
            if (is_string($cast) && str_starts_with($cast, 'decimal') && $valor !== null) {
                $valor = (float) $valor;
            }

            $salida[$columna] = self::valorSharePoint($valor, in_array($atributo, $fechas, true));
        }

        foreach ($mapa['lookups'] ?? [] as $atributo => [$columna, $modelo]) {
            if ($solo !== null && ! in_array($atributo, $solo, true)) {
                continue;
            }

            $idLocal = $this->getAttribute($atributo);
            $spId = $idLocal ? $modelo::query()->whereKey($idLocal)->value('sp_id') : null;

            // El conector arma «<interno>LookupId» a partir de esta marca
            $salida['@lookup:'.$columna] = $spId ? (int) $spId : null;
        }

        // Columnas que SharePoint repite (titulo, datos del pedido en la proforma)
        if ($solo === null) {
            foreach ($mapa['copias'] ?? [] as $columna => $ruta) {
                $valor = data_get($this, $ruta);
                $salida[$columna] = str_starts_with($columna, '@lookup:')
                    ? ($valor ? (int) $valor : null)
                    : self::valorSharePoint($valor, false);
            }
        }

        return $salida;
    }

    /**
     * Traduce un elemento leido de SharePoint (columnas por nombre visible)
     * a atributos locales.
     *
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    public static function desdeSharePoint(array $campos): array
    {
        $mapa = static::mapaSharePoint();
        $fechas = $mapa['fechas'] ?? [];
        $modelo = new static;
        $casts = $modelo->getCasts();
        $atributos = [];

        foreach ($mapa['campos'] as $atributo => $columna) {
            if (! array_key_exists($columna, $campos)) {
                continue;
            }

            $valor = $campos[$columna];

            if (is_array($valor)) {
                // Columnas de opcion leidas como {Value: "..."}
                $valor = $valor['Value'] ?? $valor['value'] ?? null;
            }

            $cast = $casts[$atributo] ?? null;

            if (is_string($cast) && enum_exists($cast)) {
                // Opcion desconocida o con otra capitalizacion: se tolera
                $atributos[$atributo] = $valor === null || $valor === ''
                    ? null
                    : (method_exists($cast, 'desde') ? $cast::desde((string) $valor) : $cast::tryFrom((string) $valor));

                continue;
            }

            if ($valor !== null && (in_array($atributo, $fechas, true) || in_array($cast, ['datetime', 'date', 'immutable_datetime'], true))) {
                $valor = Carbon::parse($valor)->setTimezone(config('app.timezone'));
                $valor = in_array($atributo, $fechas, true) ? $valor->toDateString() : $valor;
            } elseif ($cast === 'boolean') {
                $valor = (bool) $valor;
            } elseif (is_string($cast) && str_starts_with($cast, 'decimal')) {
                $valor = $valor === null || $valor === '' ? null : (float) $valor;
            } elseif (is_string($valor)) {
                $valor = trim($valor);
            }

            $atributos[$atributo] = $valor;
        }

        foreach ($mapa['lookups'] ?? [] as $atributo => [$columna, $relacionado]) {
            $spId = $campos['@lookup:'.$columna] ?? null;

            if ($spId !== null) {
                $atributos[$atributo] = $relacionado::query()->where('sp_id', $spId)->value('id');
            }
        }

        return $atributos;
    }

    private static function valorSharePoint(mixed $valor, bool $soloFecha): mixed
    {
        return match (true) {
            $valor instanceof BackedEnum => $valor->value,
            $valor instanceof CarbonInterface => $soloFecha
                ? $valor->toDateString()
                : $valor->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            default => $valor,
        };
    }
}
