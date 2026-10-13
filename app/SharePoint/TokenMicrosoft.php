<?php

namespace App\SharePoint;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tokens de aplicacion (client credentials) de Entra ID.
 *
 * Con certificado se firma un client_assertion (JWT RS256); es lo que
 * SharePoint exige para su API REST (adjuntos). Con secreto solo funciona
 * Microsoft Graph.
 */
class TokenMicrosoft
{
    public function graph(): string
    {
        return $this->token('https://graph.microsoft.com/.default');
    }

    public function sharepoint(): string
    {
        $host = config('microsoft.sharepoint.host');

        return $this->token("https://{$host}/.default");
    }

    public function configurado(): bool
    {
        return filled(config('microsoft.tenant_id')) && filled(config('microsoft.client_id'))
            && ($this->tieneCertificado() || filled(config('microsoft.client_secret')));
    }

    public function tieneCertificado(): bool
    {
        $ruta = (string) config('microsoft.certificado');

        return $ruta !== '' && is_readable(base_path($ruta)) && filled(config('microsoft.certificado_huella'));
    }

    private function token(string $alcance): string
    {
        return Cache::remember('ms-token:'.md5($alcance), now()->addMinutes(50), function () use ($alcance) {
            $tenant = config('microsoft.tenant_id');
            $cuerpo = [
                'client_id' => config('microsoft.client_id'),
                'scope' => $alcance,
                'grant_type' => 'client_credentials',
            ];

            $cuerpo += $this->credencial();

            $respuesta = Http::asForm()->timeout(20)
                ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", $cuerpo);

            if (! $respuesta->successful()) {
                throw new RuntimeException('Entra ID rechazó la credencial: '.($respuesta->json('error_description') ?? $respuesta->body()));
            }

            return (string) $respuesta->json('access_token');
        });
    }

    /**
     * Autenticacion del cliente ante Entra ID: certificado si hay, si no secreto.
     *
     * @return array<string, string>
     */
    public function credencial(): array
    {
        if ($this->tieneCertificado()) {
            return [
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $this->firmar((string) config('microsoft.tenant_id')),
            ];
        }

        return ['client_secret' => (string) config('microsoft.client_secret')];
    }

    private function firmar(string $tenant): string
    {
        $huella = hex2bin(str_replace([':', ' '], '', (string) config('microsoft.certificado_huella')));
        $cabecera = ['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $this->base64url($huella)];
        $ahora = time();
        $carga = [
            'aud' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            'iss' => config('microsoft.client_id'),
            'sub' => config('microsoft.client_id'),
            'jti' => (string) Str::uuid(),
            'nbf' => $ahora - 60,
            'exp' => $ahora + 600,
        ];

        $sinFirma = $this->base64url(json_encode($cabecera)).'.'.$this->base64url(json_encode($carga));
        $clave = openssl_pkey_get_private((string) file_get_contents(base_path((string) config('microsoft.certificado'))));

        if ($clave === false || ! openssl_sign($sinFirma, $firma, $clave, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar con el certificado de Entra ID.');
        }

        return $sinFirma.'.'.$this->base64url($firma);
    }

    private function base64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }
}
