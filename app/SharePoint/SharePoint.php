<?php

namespace App\SharePoint;

use Carbon\CarbonInterface;

/**
 * Contrato con las listas de SharePoint. Las columnas se nombran por su
 * nombre VISIBLE (el que usa la Power App) y los lookups con la marca
 * «@lookup:<columna>» => ID del elemento relacionado.
 */
interface SharePoint
{
    /** true cuando hay una conexion real configurada. */
    public function habilitado(): bool;

    /**
     * @param  array<string, mixed>  $campos
     * @return int|null ID del elemento creado (null en modo demo)
     */
    public function crear(string $lista, array $campos): ?int;

    /**
     * @param  array<string, mixed>  $campos
     */
    public function actualizar(string $lista, int $id, array $campos): void;

    public function eliminar(string $lista, int $id): void;

    /**
     * @return array<string, mixed>|null columnas por nombre visible, mas `id` y `Modified`
     */
    public function obtener(string $lista, int $id): ?array;

    /**
     * Elementos modificados desde una marca (o todos).
     *
     * @return iterable<array<string, mixed>>
     */
    public function cambios(string $lista, ?CarbonInterface $desde = null): iterable;

    /**
     * IDs de todos los elementos (para detectar borrados en una pasada completa).
     *
     * @return list<int>
     */
    public function ids(string $lista): array;

    public function adjuntar(string $lista, int $id, string $nombre, string $contenido): void;

    /**
     * @return array{nombre:string, contenido:string}|null
     */
    public function descargarAdjunto(string $lista, int $id): ?array;
}
