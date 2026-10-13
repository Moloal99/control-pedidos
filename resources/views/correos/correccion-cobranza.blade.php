@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
<x-correo titulo="Corrección de número de cobranza" :subtitulo="'Nota '.$nota.' · '.$cliente" color="#9a5b00">
    <div style="padding:16px;border-radius:10px;background:#fff7e6;border:1px solid #f3d27a;text-align:center;">
        <div style="font-size:11px;letter-spacing:1.5px;color:#9a5b00;text-transform:uppercase;font-weight:700;">Número de cobranza</div>
        <div style="font-size:20px;margin-top:6px;"><span style="text-decoration:line-through;color:#6a7592;">{{ $anterior }}</span> &nbsp;→&nbsp; <strong style="color:#063381;">{{ $nuevo }}</strong></div>
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
        <x-correo-dato etiqueta="Nota de venta">{{ $nota }}</x-correo-dato>
        <x-correo-dato etiqueta="Cliente">{{ $cliente }}</x-correo-dato>
        <x-correo-dato etiqueta="Monto">{{ $bs($monto) }}</x-correo-dato>
        <x-correo-dato etiqueta="Vendedor">{{ $vendedor }}</x-correo-dato>
    </table>
    <div style="margin-top:14px;">
        <div style="font-size:11px;letter-spacing:1.5px;color:#6a7592;text-transform:uppercase;font-weight:700;">Justificativo</div>
        <div style="margin-top:4px;">{{ $motivo }}</div>
    </div>
    <div style="font-size:12px;color:#6a7592;margin-top:14px;">Corregido por {{ $por }} el {{ $cuando }}. El monto y el saldo de la nota no cambiaron.</div>
</x-correo>
