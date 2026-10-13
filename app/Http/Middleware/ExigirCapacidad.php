<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * capacidad:puedeVender → la pantalla exige esa capacidad del usuario
 * (mismas reglas que las variables gVe... y gPuede... de la Power App).
 */
class ExigirCapacidad
{
    public function handle(Request $request, Closure $next, string $capacidad): Response
    {
        $usuario = $request->user();

        abort_unless($usuario && method_exists($usuario, $capacidad) && $usuario->{$capacidad}(), 403, 'Su rol no tiene acceso a esta pantalla.');

        return $next($request);
    }
}
