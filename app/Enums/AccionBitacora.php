<?php

namespace App\Enums;

/**
 * Tipos de asiento de la bitacora (misma base que el portal central, mas las
 * acciones propias del circuito comercial). El color de la pastilla sale de aqui.
 */
enum AccionBitacora: string
{
    case Entrar = 'entrar';
    case Salir = 'salir';
    case Ver = 'ver';
    case Abrir = 'abrir';
    case Crear = 'crear';
    case Editar = 'editar';
    case Eliminar = 'eliminar';
    case Acceso = 'acceso';
    case Exportar = 'exportar';
    case Numerar = 'numerar';
    case Despachar = 'despachar';
    case Anular = 'anular';
    case Cobrar = 'cobrar';
    case Confirmar = 'confirmar';
    case Correo = 'correo';
    case Sincronizar = 'sincronizar';
    case Rechazo = 'rechazo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entrar => 'Inicio de sesión',
            self::Salir => 'Cierre de sesión',
            self::Ver => 'Consulta',
            self::Abrir => 'Apertura de enlace',
            self::Crear => 'Alta',
            self::Editar => 'Modificación',
            self::Eliminar => 'Baja',
            self::Acceso => 'Cambio de accesos',
            self::Exportar => 'Exportación',
            self::Numerar => 'Numeración',
            self::Despachar => 'Despacho',
            self::Anular => 'Anulación',
            self::Cobrar => 'Cobranza',
            self::Confirmar => 'Confirmación',
            self::Correo => 'Correo',
            self::Sincronizar => 'Sincronización',
            self::Rechazo => 'Acceso rechazado',
        };
    }

    /** Clase de color de la pastilla. */
    public function tono(): string
    {
        return match ($this) {
            self::Entrar, self::Crear, self::Confirmar, self::Despachar => 'verde',
            self::Salir, self::Eliminar, self::Anular, self::Rechazo => 'rojo',
            self::Editar, self::Acceso, self::Numerar => 'ambar',
            self::Cobrar, self::Exportar, self::Sincronizar => 'azul',
            default => 'neutro',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_combine(
            array_map(fn (self $a) => $a->value, self::cases()),
            array_map(fn (self $a) => $a->etiqueta(), self::cases()),
        );
    }
}
