<?php

namespace App\Enums;

/**
 * Utilidades comunes de los enums de negocio: el valor es el texto exacto
 * que guarda SharePoint, la etiqueta es lo que ve el usuario.
 */
trait ConEtiqueta
{
    public function etiqueta(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string> valor => etiqueta
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = $caso->etiqueta();
        }

        return $opciones;
    }

    /** Tolerante: acepta el valor sin importar mayusculas ni espacios. */
    public static function desde(?string $valor): ?self
    {
        $valor = trim((string) $valor);

        foreach (self::cases() as $caso) {
            if (mb_strtolower($caso->value) === mb_strtolower($valor)) {
                return $caso;
            }
        }

        return null;
    }
}
