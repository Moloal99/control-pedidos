@props(['valor', 'corto' => true])
@php
    $texto = $valor === null ? '—' : (method_exists($valor, 'corto') && $corto ? $valor->corto() : (method_exists($valor, 'etiqueta') ? $valor->etiqueta() : (string) $valor));
    $tono = $valor !== null && method_exists($valor, 'tono') ? $valor->tono() : 'neutro';
@endphp
<span {{ $attributes->merge(['class' => 'pastilla tono-'.$tono]) }}>
    <span class="size-1.5 rounded-full bg-current opacity-70"></span>{{ $texto }}
</span>
