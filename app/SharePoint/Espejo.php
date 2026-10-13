<?php

namespace App\SharePoint;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unidad de trabajo «base local + SharePoint».
 *
 * Toda operacion de negocio corre dentro de `operacion()`: los cambios se
 * escriben en la base local (en una transaccion) y en SharePoint, en ese
 * orden, elemento por elemento. Si cualquier paso falla, la transaccion local
 * se revierte y lo que ya se habia escrito en SharePoint se compensa en orden
 * inverso: se borran los elementos creados y se restauran los valores
 * anteriores de los modificados. Es la version ordenada de los «rollback»
 * manuales que hacia la Power App al guardar un pedido o una nota.
 */
class Espejo
{
    /** @var list<array{0:string,1:string,2:int,3?:array<string,mixed>}> */
    private array $diario = [];

    private int $profundidad = 0;

    public function __construct(private readonly SharePoint $sharePoint) {}

    public function habilitado(): bool
    {
        return $this->sharePoint->habilitado();
    }

    public function conector(): SharePoint
    {
        return $this->sharePoint;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $trabajo
     * @return T
     */
    public function operacion(callable $trabajo): mixed
    {
        if ($this->profundidad > 0) {
            return $trabajo();
        }

        $this->diario = [];
        $this->profundidad++;

        try {
            $resultado = DB::transaction($trabajo);
            $this->diario = [];

            return $resultado;
        } catch (Throwable $e) {
            $this->compensar();

            throw $e;
        } finally {
            $this->profundidad--;
        }
    }

    /** Crea el elemento en SharePoint y guarda su ID en el modelo local. */
    public function crear(Model $modelo): void
    {
        if ($this->esSoloLectura($modelo)) {
            return;
        }

        $lista = $modelo::listaSharePoint();
        $id = $this->sharePoint->crear($lista, $modelo->paraSharePoint());

        if ($id !== null) {
            $this->diario[] = ['crear', $lista, $id];
            $modelo->forceFill(['sp_id' => $id])->saveQuietly();
        }
    }

    /**
     * Escribe en SharePoint los atributos indicados (o todos los que cambiaron
     * en la ultima grabacion local).
     *
     * @param  list<string>|null  $atributos
     */
    public function actualizar(Model $modelo, ?array $atributos = null): void
    {
        if ($this->esSoloLectura($modelo)) {
            return;
        }

        if (! $modelo->sp_id) {
            // Nunca llego a SharePoint (alta hecha en modo demo): se crea ahora
            $this->crear($modelo);

            return;
        }

        $atributos ??= array_keys($modelo->getChanges());
        $mapeables = array_merge(array_keys($modelo::mapaSharePoint()['campos']), array_keys($modelo::mapaSharePoint()['lookups'] ?? []));
        $atributos = array_values(array_intersect($atributos, $mapeables));

        if ($atributos === []) {
            return;
        }

        $lista = $modelo::listaSharePoint();
        $nuevos = $modelo->paraSharePoint($atributos);

        // Valores anteriores para poder compensar
        $anterior = (clone $modelo)->forceFill(array_intersect_key($modelo->getPrevious(), array_flip($atributos)));
        $previos = $anterior->paraSharePoint($atributos);

        $this->sharePoint->actualizar($lista, (int) $modelo->sp_id, $nuevos);
        $this->diario[] = ['actualizar', $lista, (int) $modelo->sp_id, $previos];
    }

    public function eliminar(Model $modelo): void
    {
        if ($this->esSoloLectura($modelo) || ! $modelo->sp_id) {
            return;
        }

        $lista = $modelo::listaSharePoint();
        $campos = $modelo->paraSharePoint();

        $this->sharePoint->eliminar($lista, (int) $modelo->sp_id);
        $this->diario[] = ['eliminar', $lista, (int) $modelo->sp_id, $campos];
    }

    /** Sube el comprobante como adjunto del elemento (si hay SharePoint). */
    public function adjuntar(Model $modelo, string $nombre, string $contenido): void
    {
        if (! $modelo->sp_id || ! $this->sharePoint->habilitado()) {
            return;
        }

        $this->sharePoint->adjuntar($modelo::listaSharePoint(), (int) $modelo->sp_id, $nombre, $contenido);
    }

    /**
     * Relee el elemento desde SharePoint y actualiza la copia local. Se usa
     * antes de las operaciones donde importa el ultimo valor (saldo de la
     * nota al confirmar un cobro, pendientes al despachar).
     */
    public function refrescar(Model $modelo): Model
    {
        if (! $modelo->sp_id || ! $this->sharePoint->habilitado()) {
            return $modelo;
        }

        $campos = $this->sharePoint->obtener($modelo::listaSharePoint(), (int) $modelo->sp_id);

        if ($campos !== null) {
            $modelo->forceFill($modelo::desdeSharePoint($campos))->saveQuietly();
        }

        return $modelo;
    }

    private function compensar(): void
    {
        foreach (array_reverse($this->diario) as $paso) {
            [$tipo, $lista, $id] = $paso;

            try {
                match ($tipo) {
                    'crear' => $this->sharePoint->eliminar($lista, $id),
                    'actualizar' => $this->sharePoint->actualizar($lista, $id, $paso[3] ?? []),
                    'eliminar' => $this->sharePoint->crear($lista, $paso[3] ?? []),
                };
            } catch (Throwable $e) {
                // Lo que no se pudo compensar queda en el registro para revision manual
                Log::critical('SharePoint: no se pudo deshacer un paso de una operación fallida', [
                    'paso' => $tipo, 'lista' => $lista, 'id' => $id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->diario = [];
    }

    private function esSoloLectura(Model $modelo): bool
    {
        return ! method_exists($modelo, 'mapaSharePoint') || ($modelo::mapaSharePoint()['soloLectura'] ?? false);
    }
}
