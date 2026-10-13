<?php

namespace App\Http\Middleware;

use App\Support\Rastro;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota el paso del usuario por cada pantalla (igual que el portal central).
 * Solo paginas completas: se ignoran el latido, las peticiones de Livewire y
 * todo lo que no sea HTML.
 */
class RegistrarRecorrido
{
    /** Rutas que no cuentan como «visita a una pantalla». */
    private const IGNORADAS = [
        'trazabilidad.latido',
        'trazabilidad.evento',
        'comprobantes.ver',
    ];

    /** Nombre legible de cada pantalla para la bitacora. */
    public const NOMBRES = [
        'inicio' => 'Inicio',
        'visita' => 'Visita',
        'pedido' => 'Pedido',
        'cobranza' => 'Cobranza',
        'competencia' => 'Competencia',
        'pedidos.index' => 'Pedidos',
        'pedidos.ver' => 'Detalle de pedido',
        'cobranzas.confirmar' => 'Cobranzas por confirmar',
        'clientes.ver' => 'Ficha de cliente',
        'admin.trazabilidad' => 'Administración · Trazabilidad',
        'admin.trazabilidad.sesion' => 'Administración · Detalle de sesión',
        'admin.configuracion' => 'Administración · Configuración',
        'admin.integracion' => 'Administración · Integración Microsoft 365',
        'admin.usuarios' => 'Administración · Usuarios',
        'login' => 'Acceso',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (! $this->registrable($request, $respuesta)) {
            return $respuesta;
        }

        try {
            Rastro::visitar($request, $this->titulo($request));
        } catch (\Throwable) {
            // La bitacora nunca debe tumbar una pagina
        }

        return $respuesta;
    }

    private function registrable(Request $request, Response $respuesta): bool
    {
        if (! $request->isMethod('GET') || ! $request->user()) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $request->hasHeader('X-Livewire')) {
            return false;
        }

        if ($respuesta->getStatusCode() >= 300) {
            return false;
        }

        $ruta = $request->route()?->getName();

        return $ruta !== null && ! in_array($ruta, self::IGNORADAS, true) && ! str_starts_with($ruta, 'livewire');
    }

    private function titulo(Request $request): string
    {
        $ruta = $request->route()?->getName() ?? '';

        if ($ruta === 'pedidos.ver') {
            $pedido = $request->route('pedido');

            return 'Pedido · '.(is_object($pedido) ? $pedido->numero.' · '.$pedido->cliente_nombre : (string) $pedido);
        }

        if ($ruta === 'clientes.ver') {
            $cliente = $request->route('cliente');

            return 'Cliente · '.(is_object($cliente) ? $cliente->nombre : (string) $cliente);
        }

        return self::NOMBRES[$ruta] ?? $ruta;
    }
}
