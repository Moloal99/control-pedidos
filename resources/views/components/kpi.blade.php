@props(['etiqueta', 'valor', 'icono' => 'grafico', 'detalle' => null, 'tono' => 'marca', 'href' => null])
@php
    $colores = [
        'marca' => 'from-marca-500 to-marca-700',
        'rojo' => 'from-rojo-500 to-rojo-700',
        'verde' => 'from-emerald-500 to-emerald-700',
        'ambar' => 'from-amber-400 to-amber-600',
        'violeta' => 'from-violet-500 to-violet-700',
    ];
    $etiquetaRaiz = $href ? 'a' : 'div';
@endphp
<{{ $etiquetaRaiz }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'tarjeta group relative block overflow-hidden p-4 sm:p-5 transition'.($href ? ' hover:-translate-y-0.5 hover:shadow-lg' : '')]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="rotulo">{{ $etiqueta }}</div>
            <div class="mt-1.5 truncate text-2xl font-bold tinta numero sm:text-[1.7rem]">{{ $valor }}</div>
            @if ($detalle)
                <div class="mt-1 text-xs suave">{{ $detalle }}</div>
            @endif
        </div>
        <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-lg {{ $colores[$tono] ?? $colores['marca'] }}">
            <x-icono :nombre="$icono" clase="size-5" />
        </div>
    </div>
    {{ $slot }}
</{{ $etiquetaRaiz }}>
