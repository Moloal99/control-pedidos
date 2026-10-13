@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
<x-correo titulo="Nota de venta anulada" :subtitulo="'Nota '.$nota.' · '.$cliente" :enlace="$enlace" color="#b0050f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Nota">{{ $nota }}</x-correo-dato>
        <x-correo-dato etiqueta="Pedido">{{ $pedido }}</x-correo-dato>
        <x-correo-dato etiqueta="Cliente">{{ $cliente }}</x-correo-dato>
        <x-correo-dato etiqueta="Almacén">{{ $almacen }}</x-correo-dato>
        <x-correo-dato etiqueta="Total de la nota">{{ $bs($total) }}</x-correo-dato>
    </table>
    <div style="margin:16px 0;padding:14px;border-radius:10px;background:#fdebec;border:1px solid #f5b5b9;">
        <div style="font-weight:700;color:#b0050f;margin-bottom:4px;">Motivo:</div>
        <div>{{ $motivo }}</div>
    </div>
    <div style="font-size:13px;">Anulada por <strong>{{ $por }}</strong> el {{ $cuando }}.</div>
    <div style="font-size:12px;color:#6a7592;margin-top:8px;">Las cantidades volvieron a quedar pendientes de despacho.</div>
</x-correo>
