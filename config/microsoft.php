<?php

/*
|--------------------------------------------------------------------------
| Microsoft 365 (Entra ID, Graph y SharePoint)
|--------------------------------------------------------------------------
| Una sola aplicacion registrada en Entra ID sirve para:
|  - el inicio de sesion (SSO, permisos delegados openid/profile/email/User.Read)
|  - leer y escribir las listas (permiso de aplicacion Sites.Selected o Sites.ReadWrite.All)
|  - enviar los avisos como reportesbi@ (permiso de aplicacion Mail.Send)
| Guia paso a paso: docs/03-GUIA-ENTRA-ID.md
*/

return [
    'tenant_id' => env('MS_TENANT_ID'),
    'client_id' => env('MS_CLIENT_ID'),
    'client_secret' => env('MS_CLIENT_SECRET'),

    // Certificado (recomendado; obligatorio para los adjuntos de SharePoint)
    'certificado' => env('MS_CERT_PATH'),        // .pem con la clave privada
    'certificado_huella' => env('MS_CERT_THUMBPRINT'), // huella SHA-1 en hexadecimal

    'sso' => [
        'redirect' => env('MS_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/auth/microsoft/callback'),
        // Solo cuentas de estos dominios pueden entrar
        'dominios' => array_filter(explode(',', (string) env('MS_DOMINIOS', 'plasticoscarmen.com'))),
    ],

    'sharepoint' => [
        // ej. plasticoscarmenbo.sharepoint.com
        'host' => env('SP_HOST'),
        // ej. /sites/ReportesBI (ruta del sitio donde viven las listas dim_/fact_)
        'sitio' => env('SP_SITIO'),
    ],

    'correo' => [
        // Buzon remitente de los avisos
        'remitente' => env('MS_REMITENTE', 'reportesbi@plasticoscarmen.com'),
    ],
];
