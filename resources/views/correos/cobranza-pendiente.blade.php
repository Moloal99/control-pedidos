@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
<x-correo titulo="Cobranza pendiente de confirmación" :subtitulo="'Nota '.$nota.' · '.$fecha" :enlace="$enlace" color="#04205a">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Cliente">{{ $cliente }}</x-correo-dato>
        <x-correo-dato etiqueta="Nota de venta">{{ $nota }}</x-correo-dato>
        <x-correo-dato etiqueta="Pedido">{{ $pedido }}</x-correo-dato>
        <x-correo-dato etiqueta="Vendedor de la nota">{{ $vendedor }}</x-correo-dato>
        <x-correo-dato etiqueta="Registró el cobro">{{ $registro }}</x-correo-dato>
    </table>
    <div style="margin:16px 0;padding:16px;border-radius:10px;background:#e8edf6;">
        <div style="font-size:12px;color:#6a7592;text-transform:uppercase;letter-spacing:1px;">Monto cobrado</div>
        <div style="font-size:26px;font-weight:700;color:#063381;">{{ $bs($monto) }}</div>
        <div style="font-size:13px;margin-top:4px;">{{ $forma_pago }} · {{ $tipo_pago }}</div>
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Saldo antes">{{ $bs($saldo_antes) }}</x-correo-dato>
        <x-correo-dato etiqueta="Saldo después de confirmar">{{ $bs($saldo_despues) }}</x-correo-dato>
    </table>
    <div style="margin-top:16px;padding:12px 14px;border-radius:10px;background:#fff7e6;border:1px solid #f3d27a;font-size:13px;">
        El saldo de la nota <strong>no cambió todavía</strong>. Entre a <strong>Cobranzas por confirmar</strong>, revise el comprobante y confirme el cobro con su número.
    </div>
</x-correo>
