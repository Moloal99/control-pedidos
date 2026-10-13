@props(['etiqueta'])
<tr>
    <td style="padding:6px 12px 6px 0;color:#6a7592;width:170px;vertical-align:top;font-size:13px;">{{ $etiqueta }}</td>
    <td style="padding:6px 0;font-weight:600;font-size:14px;">{{ trim((string) $slot) !== '' ? $slot : '—' }}</td>
</tr>
