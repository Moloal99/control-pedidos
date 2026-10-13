@props(['icono' => 'bandeja', 'titulo', 'texto' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <div class="grid size-14 place-items-center rounded-2xl fondo-papel-2 suave">
        <x-icono :nombre="$icono" clase="size-7" />
    </div>
    <div class="mt-3 font-semibold">{{ $titulo }}</div>
    @if ($texto)
        <div class="mt-1 max-w-sm text-sm suave">{{ $texto }}</div>
    @endif
    {{ $slot }}
</div>
