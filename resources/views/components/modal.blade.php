@props(['abierto', 'titulo', 'ancho' => 'max-w-lg'])
{{-- Ventana modal controlada por una propiedad booleana de Livewire ($abierto = 'nombrePropiedad') --}}
<div x-data="{ ver: @entangle($abierto).live }" x-show="ver" x-cloak class="fixed inset-0 z-[55] flex items-end justify-center p-0 sm:items-center sm:p-6" @keydown.escape.window="ver = false">
    <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" @click="ver = false" x-show="ver" x-transition.opacity></div>
    <div class="relative max-h-[92dvh] w-full {{ $ancho }} overflow-y-auto rounded-t-3xl p-5 shadow-2xl sm:rounded-3xl sm:p-6 fondo-papel animate-subir">
        <div class="mb-4 flex items-start justify-between gap-4">
            <h3 class="text-lg font-semibold tinta">{{ $titulo }}</h3>
            <button type="button" class="btn-fantasma btn !p-1.5" @click="ver = false" aria-label="Cerrar"><x-icono nombre="x" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
