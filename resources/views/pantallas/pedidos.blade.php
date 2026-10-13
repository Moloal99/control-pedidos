@php use App\Support\Formato; @endphp
<div class="space-y-4">
    {{-- ===== Filtros por estado ===== --}}
    <div class="desplazable -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
        @foreach (['' => ['Todos', 'caja'], 'sin_proforma' => ['Sin proforma', 'documento'], 'pendientes' => ['Por despachar', 'camion'], 'despachados' => ['Despachados', 'check-circulo']] as $clave => [$texto, $icono])
            <button type="button" wire:click="$set('estado', '{{ $clave }}')" @class(['flex shrink-0 items-center gap-2 rounded-2xl border px-4 py-2.5 text-sm font-semibold transition', 'border-marca-600 bg-marca-600 text-white shadow-lg shadow-marca-600/20' => $estado === $clave, 'linea fondo-papel hover:border-marca-300' => $estado !== $clave])>
                <x-icono :nombre="$icono" clase="size-4" /> {{ $texto }}
                <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-white/20' => $estado === $clave, 'fondo-papel-2 suave' => $estado !== $clave])>{{ $this->conteos[$clave] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="tarjeta p-3 sm:p-4">
        <div class="grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">
            <div class="relative">
                <x-icono nombre="lupa" clase="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 suave" />
                <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por cliente, vendedor o número" class="campo !pl-10">
            </div>
            @if ($usuario->veTodosLosPedidos())
                <select wire:model.live="vendedor" class="campo sm:!w-52">
                    <option value="">Todos los vendedores</option>
                    @foreach ($this->vendedores as $v)<option value="{{ $v->codigo }}">{{ $v->codigo }} — {{ $v->name }}</option>@endforeach
                </select>
            @endif
            <input type="date" wire:model.live="desde" class="campo sm:!w-40" title="Desde">
            <input type="date" wire:model.live="hasta" class="campo sm:!w-40" title="Hasta">
        </div>
    </div>

    {{-- ===== Lista ===== --}}
    <div class="tarjeta overflow-hidden">
        {{-- Escritorio: tabla --}}
        <table class="tabla hidden md:table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    @if ($usuario->veTodosLosPedidos()) <th>Vendedor</th> @endif
                    <th class="w-44">Avance</th>
                    <th>Pago</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pedidos as $p)
                    <tr wire:key="p-{{ $p->id }}" class="cursor-pointer" onclick="Livewire.navigate('{{ route('pedidos.ver', $p) }}')">
                        <td>
                            <div class="font-semibold tinta">{{ $p->numero }}</div>
                            <div class="text-xs suave">{{ Formato::relativa($p->fecha) }} · {{ $p->proformas_count }} almacén(es)</div>
                        </td>
                        <td class="max-w-64"><div class="truncate font-medium">{{ $p->cliente_nombre }}</div><div class="text-xs suave">{{ $p->forma_pago?->value }}{{ $p->dias_credito ? ' · '.$p->dias_credito.' días' : '' }}</div></td>
                        @if ($usuario->veTodosLosPedidos())
                            <td><div class="truncate text-sm">{{ $p->vendedor_nombre }}</div>@if ($p->cargado_por)<div class="text-[11px] suave">cargó {{ $p->cargado_por }}</div>@endif</td>
                        @endif
                        <td>
                            <x-progreso-pedido :estado="$p->estado_despacho" compacto />
                            <div class="mt-1"><x-estado :valor="$p->estado_despacho" /></div>
                        </td>
                        <td><x-estado :valor="$p->estado_pago" /></td>
                        <td class="text-right font-semibold numero">{{ Formato::bs($p->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-vacio icono="caja" titulo="No hay pedidos para mostrar" texto="Pruebe con otro estado o amplíe el rango de fechas." /></td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Celular: tarjetas --}}
        <div class="divide-y md:hidden linea">
            @forelse ($pedidos as $p)
                <a href="{{ route('pedidos.ver', $p) }}" wire:navigate wire:key="pm-{{ $p->id }}" class="block space-y-2 p-4 linea">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold">{{ $p->cliente_nombre }}</div>
                            <div class="text-xs suave">{{ $p->numero }} · {{ Formato::relativa($p->fecha) }}</div>
                        </div>
                        <div class="text-right font-bold numero">{{ Formato::bs($p->total) }}</div>
                    </div>
                    <x-progreso-pedido :estado="$p->estado_despacho" compacto />
                    <div class="flex flex-wrap gap-1.5"><x-estado :valor="$p->estado_despacho" /><x-estado :valor="$p->estado_pago" />@if ($usuario->veTodosLosPedidos())<span class="pastilla tono-neutro">{{ $p->vendedor_codigo }}</span>@endif</div>
                </a>
            @empty
                <x-vacio icono="caja" titulo="No hay pedidos para mostrar" />
            @endforelse
        </div>
    </div>

    <div>{{ $pedidos->links() }}</div>
</div>
