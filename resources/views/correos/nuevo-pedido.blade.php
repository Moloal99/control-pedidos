@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
@php($cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.'))
<x-correo titulo="Nuevo pedido" :subtitulo="$numero.' · '.$fecha" :enlace="$enlace">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Código cliente">{{ $cliente_codigo }}</x-correo-dato>
        <x-correo-dato etiqueta="Cliente">{{ $cliente }}</x-correo-dato>
        <x-correo-dato etiqueta="Dirección">{{ $direccion }}</x-correo-dato>
        <x-correo-dato etiqueta="Teléfono">{{ $telefono }}</x-correo-dato>
        <x-correo-dato etiqueta="Vendedor">{{ $vendedor }}</x-correo-dato>
        @if ($cargado_por)
            <x-correo-dato etiqueta="Cargado por">{{ $cargado_por }}</x-correo-dato>
        @endif
        <x-correo-dato etiqueta="Tipo de contacto">{{ $tipo_contacto }}</x-correo-dato>
        <x-correo-dato etiqueta="Forma de pago">{{ $forma_pago }}</x-correo-dato>
        <x-correo-dato etiqueta="Días de crédito">{{ $forma_pago === 'Crédito' ? $dias.' días · vence '.$vence : 'No aplica' }}</x-correo-dato>
        <x-correo-dato etiqueta="Comentario">{{ $comentario }}</x-correo-dato>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;border-collapse:collapse;font-size:13px;">
        <tr style="background:#e8edf6;color:#04205a;">
            <th align="left" style="padding:8px;">Código</th>
            <th align="left" style="padding:8px;">Descripción</th>
            <th align="right" style="padding:8px;">Cant.</th>
            <th align="right" style="padding:8px;">Unitario</th>
            <th align="right" style="padding:8px;">Total</th>
        </tr>
        @foreach ($grupos as $grupo)
            <tr>
                <td colspan="4" style="padding:12px 8px 6px;font-weight:700;color:#063381;border-bottom:2px solid #063381;">{{ $grupo['almacen'] }}</td>
                <td align="right" style="padding:12px 8px 6px;color:#063381;border-bottom:2px solid #063381;">{{ number_format($grupo['kg'], 2) }} kg</td>
            </tr>
            @foreach ($grupo['lineas'] as $l)
                <tr>
                    <td style="padding:7px 8px;border-bottom:1px solid #e0e5ef;color:#6a7592;">{{ $l['codigo'] }}</td>
                    <td style="padding:7px 8px;border-bottom:1px solid #e0e5ef;">{{ $l['descripcion'] }}</td>
                    <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;white-space:nowrap;">{{ $cant($l['cantidad']) }} {{ $l['unidad'] }}</td>
                    <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;white-space:nowrap;">{{ $bs($l['precio']) }}</td>
                    <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;font-weight:600;white-space:nowrap;">{{ $bs($l['subtotal']) }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr>
            <td colspan="3" style="padding:14px 8px;color:#6a7592;">Peso total {{ number_format($kg, 2) }} kg</td>
            <td align="right" style="padding:14px 8px;font-weight:700;">TOTAL</td>
            <td align="right" style="padding:14px 8px;font-weight:700;font-size:16px;color:#063381;white-space:nowrap;">{{ $bs($total) }}</td>
        </tr>
    </table>
</x-correo>
