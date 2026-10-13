@props(['titulo', 'subtitulo' => null, 'enlace' => null, 'color' => '#063381'])
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fa;font-family:'Segoe UI',Arial,sans-serif;color:#232c42;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fa;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="720" cellpadding="0" cellspacing="0" style="max-width:720px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e0e5ef;">
    <tr>
        <td style="background:{{ $color }};padding:22px 28px;">
            <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#ffffffbf;font-weight:600;">Plásticos Carmen · Control de Pedidos</div>
            <div style="font-size:22px;font-weight:700;color:#ffffff;margin-top:4px;">{{ $titulo }}</div>
            @if ($subtitulo)
                <div style="font-size:13px;color:#ffffffd9;margin-top:4px;">{{ $subtitulo }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:24px 28px;font-size:14px;line-height:1.55;">
            {{ $slot }}
        </td>
    </tr>
    @if ($enlace)
    <tr>
        <td style="padding:0 28px 24px;">
            <a href="{{ $enlace }}" style="display:inline-block;background:#E00613;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 20px;border-radius:9px;">Abrir en el sistema</a>
        </td>
    </tr>
    @endif
    <tr>
        <td style="padding:14px 28px;background:#f4f6fa;font-size:11px;color:#6a7592;">
            Correo automático del sistema Control de Pedidos de Plásticos Carmen. No responda a este mensaje.
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
