@php($bs = fn ($v) => 'Bs '.number_format((float) $v, 2))
@php($cant = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.'))
@php($tachado = 'text-decoration:line-through;color:#6a7592;')
@php($pastilla = fn ($fondo, $texto) => "display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:{$fondo};color:{$texto};")
<x-correo titulo="Proforma modificada · {{ $almacen }}" :subtitulo="'Proforma '.($numero ?: 'sin número').' · pedido '.$pedido" :enlace="$enlace">
    <div style="padding:10px 14px;border-radius:10px;background:#fff7e6;border:1px solid #f3d27a;font-size:13px;">
        Modificado por <strong>{{ $por }}</strong> el {{ $cuando }}
    </div>

    <div style="margin-top:18px;font-size:11px;letter-spacing:1.5px;color:#6a7592;text-transform:uppercase;font-weight:700;">Qué cambió en esta proforma</div>
    <ul style="padding-left:18px;margin:8px 0;">
        @foreach ($cabecera as $linea)
            <li style="margin:4px 0;">{{ $linea }}</li>
        @endforeach
        @foreach ($cambios['nuevos'] ?? [] as $n)
            <li style="margin:4px 0;"><span style="{{ $pastilla('#e7f5ec', '#15693a') }}">NUEVO</span> {{ $n['descripcion'] }} — {{ $cant($n['cantidad']) }} {{ $n['unidad'] }} × {{ $bs($n['precio']) }} = <strong>{{ $bs($n['cantidad'] * $n['precio']) }}</strong></li>
        @endforeach
        @foreach ($cambios['modificados'] ?? [] as $m)
            <li style="margin:4px 0;"><span style="{{ $pastilla('#fff1d6', '#9a5b00') }}">CAMBIO</span> {{ $m['descripcion'] }} —
                @if ($m['cantidad_antes'] != $m['cantidad'])
                    cantidad <span style="{{ $tachado }}">{{ $cant($m['cantidad_antes']) }}</span> → {{ $cant($m['cantidad']) }} {{ $m['unidad'] }}
                @endif
                @if ($m['precio_antes'] != $m['precio'])
                    · precio <span style="{{ $tachado }}">{{ $bs($m['precio_antes']) }}</span> → {{ $bs($m['precio']) }}
                @endif
                · subtotal <span style="{{ $tachado }}">{{ $bs($m['cantidad_antes'] * $m['precio_antes']) }}</span> → <strong>{{ $bs($m['cantidad'] * $m['precio']) }}</strong>
            </li>
        @endforeach
        @foreach ($cambios['eliminados'] ?? [] as $e)
            <li style="margin:4px 0;"><span style="{{ $pastilla('#fdebec', '#b0050f') }}">ELIMINADO</span> <span style="{{ $tachado }}">{{ $e['descripcion'] }}</span> — eran {{ $cant($e['cantidad']) }} {{ $e['unidad'] }} × {{ $bs($e['precio']) }}</li>
        @endforeach
    </ul>
    <div style="font-size:13px;">
        Subtotal de la proforma:
        @if (abs($total_antes - $total_ahora) > 0.004)
            <span style="{{ $tachado }}">{{ $bs($total_antes) }}</span> → <strong>{{ $bs($total_ahora) }}</strong>
        @else
            <strong>{{ $bs($total_ahora) }}</strong> (sin cambio)
        @endif
        · {{ number_format($kg, 2) }} kg
    </div>

    <div style="margin-top:20px;font-size:11px;letter-spacing:1.5px;color:#6a7592;text-transform:uppercase;font-weight:700;">Datos</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <x-correo-dato etiqueta="Cliente">{{ $cliente }} ({{ $cliente_codigo }})</x-correo-dato>
        <x-correo-dato etiqueta="Dirección">{{ $direccion }}</x-correo-dato>
        <x-correo-dato etiqueta="Teléfono">{{ $telefono }}</x-correo-dato>
        <x-correo-dato etiqueta="Vendedor">{{ $vendedor }}</x-correo-dato>
        <x-correo-dato etiqueta="Fecha del pedido">{{ $fecha_pedido }}</x-correo-dato>
        <x-correo-dato etiqueta="Forma de pago">{{ $forma_pago }}{{ $forma_pago === 'Crédito' ? ' · '.$dias.' días · vence '.$vence : '' }}</x-correo-dato>
    </table>

    <div style="margin-top:20px;font-size:11px;letter-spacing:1.5px;color:#6a7592;text-transform:uppercase;font-weight:700;">Productos de esta proforma</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;border-collapse:collapse;font-size:13px;">
        <tr style="background:#e8edf6;color:#04205a;">
            <th align="left" style="padding:8px;">Código</th>
            <th align="left" style="padding:8px;">Producto</th>
            <th align="right" style="padding:8px;">Cantidad</th>
            <th align="right" style="padding:8px;">Precio Bs</th>
            <th align="right" style="padding:8px;">Subtotal Bs</th>
        </tr>
        @foreach ($lineas as $l)
            <tr style="{{ $l['resaltar'] ? 'background:#FFFBEF;' : '' }}">
                <td style="padding:7px 8px;border-bottom:1px solid #e0e5ef;color:#6a7592;">{{ $l['codigo'] }}</td>
                <td style="padding:7px 8px;border-bottom:1px solid #e0e5ef;">{{ $l['descripcion'] }}</td>
                <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;white-space:nowrap;">{{ $cant($l['cantidad']) }} {{ $l['unidad'] }}</td>
                <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;">{{ number_format($l['precio'], 2) }}</td>
                <td align="right" style="padding:7px 8px;border-bottom:1px solid #e0e5ef;font-weight:600;">{{ number_format($l['subtotal'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4" align="right" style="padding:12px 8px;font-weight:700;">SUBTOTAL PROFORMA</td>
            <td align="right" style="padding:12px 8px;font-weight:700;color:#063381;">{{ $bs($total_ahora) }}</td>
        </tr>
    </table>
    <div style="font-size:12px;color:#6a7592;margin-top:10px;">Total del pedido completo: {{ $bs($total_pedido) }} · este correo cubre solo {{ $almacen }}.</div>
</x-correo>
