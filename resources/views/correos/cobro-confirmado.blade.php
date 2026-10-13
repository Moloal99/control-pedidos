@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
<x-correo titulo="Cobro confirmado" :subtitulo="'Nota '.$nota.' · N° de cobranza '.$numero" color="#15693a">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Nota de venta">{{ $nota }}</x-correo-dato>
        <x-correo-dato etiqueta="Cliente">{{ $cliente }}</x-correo-dato>
        <x-correo-dato etiqueta="Vendedor">{{ $vendedor }}</x-correo-dato>
        <x-correo-dato etiqueta="N° de cobranza">{{ $numero }}</x-correo-dato>
    </table>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;border-collapse:collapse;font-size:14px;">
        <tr><td style="padding:8px;border-bottom:1px solid #e0e5ef;">Monto cobrado</td><td align="right" style="padding:8px;border-bottom:1px solid #e0e5ef;font-weight:700;">{{ $bs($monto) }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #e0e5ef;">Forma de pago</td><td align="right" style="padding:8px;border-bottom:1px solid #e0e5ef;">{{ $forma_pago }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #e0e5ef;">Total de la nota</td><td align="right" style="padding:8px;border-bottom:1px solid #e0e5ef;">{{ $bs($nota_total) }}</td></tr>
        <tr><td style="padding:8px;border-bottom:1px solid #e0e5ef;">Pagado acumulado</td><td align="right" style="padding:8px;border-bottom:1px solid #e0e5ef;">{{ $bs($pagado) }}</td></tr>
        <tr><td style="padding:8px;">Saldo pendiente</td><td align="right" style="padding:8px;font-weight:700;color:#063381;">{{ $bs($saldo) }}</td></tr>
    </table>
    <div style="font-size:13px;margin-top:14px;">Confirmado por <strong>{{ $por }}</strong> el {{ $cuando }}.</div>
    @if ($saldo <= 0)
        <div style="margin-top:10px;font-weight:700;color:#15693a;">La nota quedó totalmente pagada.</div>
    @endif
</x-correo>
