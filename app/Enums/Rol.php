<?php

namespace App\Enums;

/**
 * Roles de dim_VENDEDORES.VENDEDOR_ROL. «user» es el vendedor de campo.
 */
enum Rol: string
{
    use ConEtiqueta;

    case Admin = 'admin';
    case Almacen = 'almacen';
    case Cobranzas = 'cobranzas';
    case Cobrador = 'cobrador';
    case Vendedor = 'user';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Almacen => 'Almacén',
            self::Cobranzas => 'Cobranzas',
            self::Cobrador => 'Cobrador',
            self::Vendedor => 'Vendedor',
        };
    }
}
