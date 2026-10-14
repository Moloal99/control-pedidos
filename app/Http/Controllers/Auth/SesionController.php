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
 * Pantalla de acceso y cierre de sesion.
 *
 * El acceso de demostracion (elegir un usuario sin contraseña) existe solo en
 * modo demo. Fuera del equipo local (por ejemplo en Render) exige ademas la
 * clave PEDIDOS_DEMO_CLAVE; sin esa clave configurada, en produccion no existe.
 */
class SesionController extends Controller
{
    public function mostrar(Request $request, TokenMicrosoft $token): View
    {
        $demo = self::demoHabilitado();
        $desbloqueada = $demo && self::demoDesbloqueada($request);

        return view('auth.login', [
            'ssoListo' => $token->configurado(),
            'demo' => $demo,
            'pideClave' => $demo && ! $desbloqueada,
            'usuarios' => $desbloqueada
                ? User::query()->where('activo', true)->orderBy('rol')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function clave(Request $request): RedirectResponse
    {
        abort_unless(self::demoHabilitado() && filled(self::claveDemo()), 404);

        if (! hash_equals((string) self::claveDemo(), (string) $request->input('clave'))) {
            Rastro::registrar(AccionBitacora::Rechazo, 'Clave de demostración incorrecta', null, null, ['ip' => $request->ip()]);

            return back()->with('error', 'La clave de demostración no es correcta.');
        }

        $request->session()->put('demo_desbloqueada', true);

        return redirect()->route('login');
    }

    public function demo(Request $request): RedirectResponse
    {
        abort_unless(self::demoHabilitado() && self::demoDesbloqueada($request), 404);

        $usuario = User::query()->where('activo', true)->findOrFail($request->integer('usuario'));

        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('demo_desbloqueada', true);
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

    private static function claveDemo(): ?string
    {
        return config('pedidos.demo_clave') ?: null;
    }

    public static function demoHabilitado(): bool
    {
        return config('pedidos.modo') === 'demo' && (! app()->isProduction() || filled(self::claveDemo()));
    }

    /** Sin clave configurada (equipo local) no se pide nada. */
    private static function demoDesbloqueada(Request $request): bool
    {
        return blank(self::claveDemo()) || $request->session()->get('demo_desbloqueada') === true;
    }
}
