<?php

namespace App\SharePoint;

use Carbon\CarbonInterface;

/**
 * Modo demo: no hay SharePoint. La base local es la fuente de verdad y nada
 * sale del servidor. Permite desarrollar y capacitar sin tocar produccion.
 */
class SharePointNulo implements SharePoint
{
    public function habilitado(): bool
    {
        return false;
    }

    public function crear(string $lista, array $campos): ?int
    {
        return null;
    }

    public function actualizar(string $lista, int $id, array $campos): void {}

    public function eliminar(string $lista, int $id): void {}

    public function obtener(string $lista, int $id): ?array
    {
        return null;
    }

    public function cambios(string $lista, ?CarbonInterface $desde = null): iterable
    {
        return [];
    }

    public function ids(string $lista): array
    {
        return [];
    }

    public function adjuntar(string $lista, int $id, string $nombre, string $contenido): void {}

    public function descargarAdjunto(string $lista, int $id): ?array
    {
        return null;
    }
}
