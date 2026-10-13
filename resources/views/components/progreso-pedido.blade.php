@props(['estado', 'compacto' => false])
{{-- Recorrido del pedido: Cargado → Proformado → Despacho parcial → Despachado --}}
@php
    $paso = $estado?->paso() ?? 1;
    $pasos = ['Cargado', 'Proformado', 'En despacho', 'Despachado'];
@endphp
@if ($compacto)
    <div class="flex items-center gap-1" title="{{ $estado?->value }}">
        @foreach ($pasos as $i => $nombre)
            <span @class([
                'h-1.5 flex-1 rounded-full',
                'bg-emerald-500' => $paso === 4,
                'bg-marca-500' => $paso < 4 && $i < $paso,
                'bg-[var(--borde)]' => $paso < 4 && $i >= $paso,
            ])></span>
        @endforeach
    </div>
@else
    <ol class="flex items-center">
        @foreach ($pasos as $i => $nombre)
            @php($hecho = $i < $paso)
            <li class="flex flex-1 items-center last:flex-none">
                <div class="flex flex-col items-center gap-1">
                    <span @class([
                        'grid size-8 place-items-center rounded-full text-xs font-bold ring-4 transition',
                        'bg-emerald-500 text-white ring-emerald-500/15' => $hecho && $paso === 4,
                        'bg-marca-600 text-white ring-marca-500/15' => $hecho && $paso < 4,
                        'fondo-papel-2 suave ring-transparent border linea' => ! $hecho,
                    ])>
                        @if ($hecho) <x-icono nombre="check" clase="size-4" :trazo="2.6" /> @else {{ $i + 1 }} @endif
                    </span>
                    <span @class(['text-[11px] font-medium whitespace-nowrap', 'tinta' => $hecho, 'suave' => ! $hecho])>{{ $nombre }}</span>
                </div>
                @if (! $loop->last)
                    <span @class(['mx-1.5 mb-5 h-0.5 flex-1 rounded-full', 'bg-marca-500' => $i + 1 < $paso, 'bg-[var(--borde)]' => $i + 1 >= $paso])></span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
