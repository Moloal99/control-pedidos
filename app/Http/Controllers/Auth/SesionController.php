<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionBitacora;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\SharePoint\TokenMicrosoft;
use App\Support\Rastro;
use App\Support\VentaEnCurso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pantalla de acceso y cierre de sesion. El acceso de demostracion (elegir
 * un usuario sin contraseña) solo existe en modo demo y fuera de produccion.
 */
class SesionController extends Controller
{
    public function mostrar(TokenMicrosoft $token): View
    {
        return view('auth.login', [
            'ssoListo' => $token->configurado(),
            'demo' => self::demoHabilitado(),
            'usuarios' => self::demoHabilitado()
                ? User::query()->where('activo', true)->orderBy('rol')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function demo(Request $request): RedirectResponse
    {
        abort_unless(self::demoHabilitado(), 404);

        $usuario = User::query()->where('activo', true)->findOrFail($request->integer('usuario'));

        Auth::login($usuario);
        $request->session()->regenerate();
        $usuario->forceFill(['ultimo_acceso_at' => now()])->save();

        Rastro::olvidar();
        Rastro::sesion($request);
        Rastro::registrar(AccionBitacora::Entrar, 'Inició sesión (modo demostración)');

        return redirect()->route('inicio');
    }

    public function salir(Request $request): RedirectResponse
    {
        Rastro::registrar(AccionBitacora::Salir, 'Cerró sesión');
        Rastro::cerrar('salida', $request);
        VentaEnCurso::reiniciar();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public static function demoHabilitado(): bool
    {
        return config('pedidos.modo') === 'demo' && ! app()->isProduction();
    }
}
