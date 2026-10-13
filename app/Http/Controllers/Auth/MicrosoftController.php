<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccionBitacora;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\SharePoint\TokenMicrosoft;
use App\Support\Rastro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Inicio de sesion con Microsoft 365 (OpenID Connect, flujo de codigo con
 * PKCE). La cuenta tiene que existir en dim_VENDEDORES: a diferencia de la
 * Power App, un usuario no registrado NO entra como vendedor.
 */
class MicrosoftController extends Controller
{
    public function __construct(private readonly TokenMicrosoft $token) {}

    public function redirigir(Request $request): RedirectResponse
    {
        abort_unless($this->token->configurado(), 503, 'El inicio de sesión con Microsoft todavía no está configurado.');

        $estado = Str::random(40);
        $verificador = Str::random(64);
        $request->session()->put('ms_oauth', ['estado' => $estado, 'verificador' => $verificador]);

        $consulta = http_build_query([
            'client_id' => config('microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('microsoft.sso.redirect'),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $estado,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verificador, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://login.microsoftonline.com/'.config('microsoft.tenant_id').'/oauth2/v2.0/authorize?'.$consulta);
    }

    public function volver(Request $request): RedirectResponse
    {
        $guardado = $request->session()->pull('ms_oauth');

        if ($request->filled('error')) {
            return $this->rechazar('Microsoft no autorizó el ingreso: '.$request->string('error_description')->limit(200));
        }

        if (! $guardado || ! hash_equals($guardado['estado'], (string) $request->query('state'))) {
            return $this->rechazar('La solicitud de ingreso venció. Vuelva a intentarlo.');
        }

        $respuesta = Http::asForm()->timeout(20)->post(
            'https://login.microsoftonline.com/'.config('microsoft.tenant_id').'/oauth2/v2.0/token',
            [
                'client_id' => config('microsoft.client_id'),
                'grant_type' => 'authorization_code',
                'code' => (string) $request->query('code'),
                'redirect_uri' => config('microsoft.sso.redirect'),
                'code_verifier' => $guardado['verificador'],
                'scope' => 'openid profile email User.Read',
            ] + $this->token->credencial(),
        );

        if (! $respuesta->successful()) {
            report(new \RuntimeException('SSO: '.$respuesta->body()));

            return $this->rechazar('No se pudo validar su cuenta de Microsoft. Vuelva a intentarlo.');
        }

        $perfil = Http::withToken((string) $respuesta->json('access_token'))
            ->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'id,displayName,mail,userPrincipalName']);

        $correo = mb_strtolower(trim((string) ($perfil->json('mail') ?: $perfil->json('userPrincipalName'))));
        $dominio = str($correo)->after('@')->toString();

        if ($correo === '' || ! in_array($dominio, config('microsoft.sso.dominios'), true)) {
            return $this->rechazar('Solo pueden ingresar cuentas de Plásticos Carmen.');
        }

        $usuario = User::query()->where('email', $correo)->first();

        if (! $usuario || ! $usuario->activo) {
            Rastro::registrar(AccionBitacora::Rechazo, "Intento de ingreso de {$correo}: no está registrado en dim_VENDEDORES", null, null, ['correo' => $correo]);

            return $this->rechazar("La cuenta {$correo} no está habilitada en el sistema. Pida a Sistemas que la registre en la lista de vendedores.");
        }

        $usuario->forceFill(['ms_oid' => $perfil->json('id'), 'ultimo_acceso_at' => now()])->save();

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        Rastro::olvidar();
        Rastro::sesion($request);
        Rastro::registrar(AccionBitacora::Entrar, 'Inició sesión con Microsoft 365');

        return redirect()->intended(route('inicio'));
    }

    private function rechazar(string $mensaje): RedirectResponse
    {
        return redirect()->route('login')->with('error', $mensaje);
    }
}
