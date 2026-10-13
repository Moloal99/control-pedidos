<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use App\Models\Visita;

/**
 * Estado de la venta que el usuario esta armando: la visita registrada, a
 * nombre de quien se carga y el carrito. Vive en la sesion del servidor (en
 * la Power App eran variables globales que se perdian al cerrar la app).
 */
class VentaEnCurso
{
    private const CLAVE = 'venta_en_curso';

    public static function visita(): ?Visita
    {
        $id = session(self::CLAVE.'.visita_id');

        return $id ? Visita::query()->with('cliente')->find($id) : null;
    }

    public static function fijarVisita(Visita $visita): void
    {
        session([self::CLAVE.'.visita_id' => $visita->getKey(), self::CLAVE.'.guardado' => null]);
    }

    /** Vendedor atribuido (a nombre de quien se carga). Por omision, el usuario. */
    public static function vendedor(User $usuario): User
    {
        $id = session(self::CLAVE.'.vendedor_id');

        if (! $id || ! $usuario->puedeElegirVendedor() || $id === $usuario->getKey()) {
            return $usuario;
        }

        return User::query()->cargables()->find($id) ?? $usuario;
    }

    public static function fijarVendedor(?int $id): void
    {
        session([self::CLAVE.'.vendedor_id' => $id]);
    }

    /**
     * @return list<array{producto_id:int, cantidad:float, precio:float}>
     */
    public static function carrito(): array
    {
        return array_values(session(self::CLAVE.'.carrito', []));
    }

    /**
     * @param  list<array{producto_id:int, cantidad:float, precio:float}>  $carrito
     */
    public static function fijarCarrito(array $carrito): void
    {
        session([self::CLAVE.'.carrito' => array_values($carrito)]);
    }

    /**
     * Carrito con los productos cargados (para mostrar).
     *
     * @return list<array{producto:Producto, cantidad:float, precio:float}>
     */
    public static function carritoDetallado(): array
    {
        $carrito = self::carrito();
        $productos = Producto::query()->whereIn('id', array_column($carrito, 'producto_id'))->get()->keyBy('id');

        return array_values(array_filter(array_map(fn ($item) => isset($productos[$item['producto_id']])
            ? ['producto' => $productos[$item['producto_id']], 'cantidad' => (float) $item['cantidad'], 'precio' => (float) $item['precio']]
            : null, $carrito)));
    }

    /** Ultimo pedido guardado en esta visita (para el aviso de exito). */
    public static function guardado(): ?array
    {
        return session(self::CLAVE.'.guardado');
    }

    public static function marcarGuardado(string $numero, int $lineas, float $total): void
    {
        session([self::CLAVE.'.guardado' => compact('numero', 'lineas', 'total'), self::CLAVE.'.carrito' => []]);
    }

    public static function olvidarGuardado(): void
    {
        session([self::CLAVE.'.guardado' => null]);
    }

    /** «Nueva visita»: limpia todo y la atribucion vuelve al usuario. */
    public static function reiniciar(): void
    {
        session()->forget(self::CLAVE);
    }
}
