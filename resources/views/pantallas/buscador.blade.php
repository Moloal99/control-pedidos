<div x-data="{ abierto: false }" @abrir-buscador.window="abierto = true; $nextTick(() => $refs.entrada.focus())" @keydown.escape.window="abierto = false">
    <div x-show="abierto" x-cloak class="fixed inset-0 z-[58] flex items-start justify-center p-3 pt-[8vh] sm:p-6 sm:pt-[12vh]">
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="abierto = false" x-transition.opacity></div>
        <div class="tarjeta relative w-full max-w-xl overflow-hidden shadow-2xl animate-entrar" @click.outside="abierto = false">
            <div class="flex items-center gap-3 border-b px-4 linea">
                <x-icono nombre="lupa" clase="size-5 suave" />
                <input x-ref="entrada" type="search" wire:model.live.debounce.200ms="texto" placeholder="Buscar pedidos, clientes, notas o productos…"
                       class="h-14 flex-1 bg-transparent text-base outline-none" autocomplete="off">
                <kbd class="rounded-md border px-1.5 text-[10px] font-semibold suave linea">Esc</kbd>
            </div>
            <div class="desplazable max-h-[60vh] overflow-y-auto p-2">
                @if (mb_strlen(trim($texto)) < 2)
                    <div class="rotulo px-3 pt-2 pb-1">Accesos rápidos</div>
                    @foreach ($accesos as $a)
                        <a href="{{ $a['url'] }}" wire:navigate @click="abierto = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-[var(--papel-2)]">
                            <span class="grid size-8 place-items-center rounded-lg bg-marca-50 text-marca-600 dark:bg-marca-500/15 dark:text-marca-200"><x-icono :nombre="$a['icono']" clase="size-4" /></span>
                            <span class="text-sm font-medium">{{ $a['titulo'] }}</span>
                        </a>
                    @endforeach
                @else
                    @forelse ($this->resultados as $grupo => $items)
                        <div class="rotulo px-3 pt-3 pb-1">{{ $grupo }}</div>
                        @foreach ($items as $r)
                            <a href="{{ $r['url'] ?? '#' }}" wire:navigate @click="abierto = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-[var(--papel-2)]">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg fondo-papel-2 suave"><x-icono :nombre="$r['icono']" clase="size-4" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ $r['titulo'] }}</span>
                                    <span class="block truncate text-xs suave">{{ $r['detalle'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    @empty
                        <x-vacio icono="lupa" titulo="Sin resultados" texto="Pruebe con otro nombre, código o número." class="!py-8" />
                    @endforelse
                @endif
            </div>
        </div>
    </div>
</div>
