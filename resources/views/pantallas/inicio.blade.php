@php
    use App\Support\Formato;
    $i = $this->indicadores;
    $maxTend = max(1, collect($this->tendencia)->max('total'));
@endphp
<div class="space-y-6">
    {{-- ===== Saludo ===== --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-marca-600 via-marca-700 to-marca-900 p-6 text-white shadow-xl shadow-marca-900/20 sm:p-8">
        <div class="absolute -top-20 -right-16 size-72 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 left-1/3 size-72 rounded-full bg-rojo-600/30 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 md:flex-row md:items-end">
            <div class="flex-1">
                <div class="text-sm text-white/70">{{ ucfirst($hoy->translatedFormat('l j \d\e F')) }}</div>
                <h2 class="mt-1 text-2xl font-bold sm:text-3xl">{{ Formato::saludo() }}, {{ str($usuario->name)->before(' ') }}</h2>
                <p class="mt-1 text-white/80">
                    @if ($usuario->esAlmacen()) Hay {{ $i['sin_proforma'] }} pedido(s) por proformar y {{ $i['por_despachar'] }} por despachar.
                    @elseif ($usuario->esCobranzas()) Tiene {{ $i['por_confirmar'] }} cobranza(s) esperando confirmación.
                    @elseif ($usuario->esCobrador()) Hay {{ Formato::bs($i['vencido']) }} vencido por cobrar.
                    @else Lleva {{ Formato::bs($i['ventas_mes']) }} vendidos este mes en {{ $i['pedidos_mes'] }} pedido(s).
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($usuario->puedeVender())
                    <a href="{{ route('visita') }}" wire:navigate class="btn bg-white text-marca-700 shadow-lg hover:bg-marca-50"><x-icono nombre="mas" clase="size-4" /> Nueva visita</a>
                @endif
                @if ($usuario->puedeCobrar())
                    <a href="{{ route('cobranza') }}" wire:navigate class="btn bg-white/15 text-white backdrop-blur hover:bg-white/25"><x-icono nombre="billete" clase="size-4" /> Cobrar</a>
                @endif
                @if ($usuario->veTabCobranzas())
                    <a href="{{ route('cobranzas.confirmar') }}" wire:navigate class="btn bg-white text-marca-700 shadow-lg hover:bg-marca-50"><x-icono nombre="recibo" clase="size-4" /> Confirmar cobros</a>
                @endif
                @if ($usuario->esAlmacen())
                    <a href="{{ route('pedidos.index', ['estado' => 'sin_proforma']) }}" wire:navigate class="btn bg-white text-marca-700 shadow-lg hover:bg-marca-50"><x-icono nombre="camion" clase="size-4" /> Ir a despachar</a>
                @endif
            </div>
        </div>
    </section>

    {{-- ===== Venta en curso ===== --}}
    @if ($visita && $usuario->puedeVender())
        <a href="{{ route($visita->esPedido() ? 'pedido' : 'visita') }}" wire:navigate class="tarjeta flex items-center gap-4 p-4 transition hover:shadow-lg animate-entrar">
            <span class="relative grid size-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15"><x-icono nombre="ubicacion" /><span class="absolute -top-0.5 -right-0.5 size-3 rounded-full bg-emerald-500 ring-2 ring-[var(--papel)] animate-latir"></span></span>
            <div class="min-w-0 flex-1">
                <div class="rotulo">Visita en curso</div>
                <div class="truncate font-semibold">{{ $visita->cliente_nombre }}</div>
            </div>
            @if ($carrito)<span class="pastilla tono-azul">{{ $carrito }} en el carrito</span>@endif
            <x-icono nombre="chevron-der" clase="size-5 suave" />
        </a>
    @endif

    {{-- ===== Indicadores ===== --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @if ($usuario->puedeVender() || $usuario->esAdmin())
            <x-kpi etiqueta="Ventas del mes" :valor="Formato::compacto($i['ventas_mes'])" icono="grafico" :detalle="$i['pedidos_mes'].' pedido(s)'" :href="route('pedidos.index')" />
            <x-kpi etiqueta="Visitas hoy" :valor="$i['visitas_hoy']" icono="ubicacion" tono="violeta" />
        @endif
        @if ($usuario->esAlmacen() || $usuario->esAdmin())
            <x-kpi etiqueta="Sin proforma" :valor="$i['sin_proforma']" icono="documento" tono="ambar" detalle="Esperan número del ERP" :href="route('pedidos.index', ['estado' => 'sin_proforma'])" />
            <x-kpi etiqueta="Por despachar" :valor="$i['por_despachar']" icono="camion" :detalle="'Despachado hoy '.Formato::compacto($i['despachado_hoy'])" :href="route('pedidos.index', ['estado' => 'pendientes'])" />
        @endif
        @if ($usuario->puedeCobrar() || $usuario->esCobranzas())
            <x-kpi etiqueta="Por cobrar" :valor="Formato::compacto($i['por_cobrar'])" icono="billete" tono="rojo" :detalle="'Vencido '.Formato::compacto($i['vencido'])" :href="$usuario->puedeCobrar() ? route('cobranza') : null" />
        @endif
        @if ($usuario->puedeConfirmarCobranzas())
            <x-kpi etiqueta="Por confirmar" :valor="$i['por_confirmar']" icono="reloj" tono="ambar" :detalle="Formato::bs($i['por_confirmar_bs'])" :href="route('cobranzas.confirmar')" />
        @endif
        <x-kpi etiqueta="Cobrado en el mes" :valor="Formato::compacto($i['confirmado_mes'])" icono="check-circulo" tono="verde" detalle="Cobros confirmados" />
    </section>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-5">
            {{-- ===== Tendencia ===== --}}
            @if (! $usuario->esCobrador())
                <section class="tarjeta p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">Pedidos de los últimos 14 días</h3>
                            <div class="text-xs suave">{{ $usuario->veTodosLosPedidos() ? 'Toda la empresa' : 'Sus pedidos' }} · {{ Formato::bs(collect($this->tendencia)->sum('total')) }}</div>
                        </div>
                    </div>
                    <div class="mt-5 flex h-40 items-end gap-1.5">
                        @foreach ($this->tendencia as $t)
                            @php($alto = $t['total'] > 0 ? max(4, round($t['total'] / $maxTend * 100)) : 2)
                            <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ $t['dia']->format('d/m') }} · {{ Formato::bs($t['total']) }}">
                                <div @class(['w-full rounded-t-md transition-all group-hover:opacity-80', 'bg-gradient-to-t from-marca-600 to-marca-400' => ! $t['dia']->isToday(), 'bg-gradient-to-t from-rojo-600 to-rojo-500' => $t['dia']->isToday(), '!bg-[var(--borde)]' => $t['total'] <= 0]) style="height: {{ $alto }}%"></div>
                                <div class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded-md bg-slate-900 px-2 py-1 text-[10px] whitespace-nowrap text-white group-hover:block">{{ Formato::compacto($t['total']) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-1.5 text-center text-[10px] suave">
                        @foreach ($this->tendencia as $t)<div class="flex-1">{{ $t['dia']->format('d') }}</div>@endforeach
                    </div>
                </section>
            @endif

            {{-- ===== Bandeja de despacho ===== --}}
            @if ($this->bandeja->isNotEmpty())
                <section class="tarjeta overflow-hidden">
                    <div class="flex items-center justify-between border-b px-5 py-3.5 linea">
                        <h3 class="font-semibold">Bandeja de despacho</h3>
                        <a href="{{ route('pedidos.index', ['estado' => 'pendientes']) }}" wire:navigate class="text-xs font-semibold text-marca-600">Ver todo</a>
                    </div>
                    <div class="divide-y linea">
                        @foreach ($this->bandeja as $p)
                            <a href="{{ route('pedidos.ver', $p) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 linea hover:bg-[var(--papel-2)]">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $p->cliente_nombre }}</div>
                                    <div class="text-xs suave">{{ $p->numero }} · {{ Formato::relativa($p->fecha) }}</div>
                                </div>
                                <x-estado :valor="$p->estado_despacho" />
                                <x-icono nombre="chevron-der" clase="size-4 suave" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ===== Cobranzas por confirmar ===== --}}
            @if ($this->cobranzasPendientes->isNotEmpty())
                <section class="tarjeta overflow-hidden">
                    <div class="flex items-center justify-between border-b px-5 py-3.5 linea">
                        <h3 class="font-semibold">Cobranzas esperando confirmación</h3>
                        <a href="{{ route('cobranzas.confirmar') }}" wire:navigate class="text-xs font-semibold text-marca-600">Ver todo</a>
                    </div>
                    <div class="divide-y linea">
                        @foreach ($this->cobranzasPendientes as $c)
                            <a href="{{ route('cobranzas.confirmar', ['cobranza' => $c->id]) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 linea hover:bg-[var(--papel-2)]">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ $c->cliente_nombre }}</div>
                                    <div class="text-xs suave">Nota {{ $c->nota_numero }} · {{ $c->vendedor_nombre }} · {{ Formato::relativa($c->fecha) }}</div>
                                </div>
                                <span class="font-semibold numero">{{ Formato::bs($c->monto) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-5">
            {{-- ===== Notas vencidas ===== --}}
            @if ($this->notasVencidas->isNotEmpty())
                <section class="tarjeta overflow-hidden">
                    <div class="flex items-center gap-2 border-b px-5 py-3.5 linea">
                        <x-icono nombre="alerta" clase="size-4 text-rojo-600" />
                        <h3 class="font-semibold">Notas vencidas para cobrar</h3>
                    </div>
                    <div class="divide-y linea">
                        @foreach ($this->notasVencidas as $n)
                            <a href="{{ route('cobranza', ['nota' => $n->id]) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 linea hover:bg-[var(--papel-2)]">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">{{ $n->cliente_nombre }}</div>
                                    <div class="text-xs text-rojo-600">Nota {{ $n->numero }} · venció {{ $n->fecha_vencimiento->diffForHumans() }}</div>
                                </div>
                                <span class="text-sm font-semibold numero">{{ Formato::bs($n->saldo_pendiente) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ===== Actividad ===== --}}
            <section class="tarjeta p-5">
                <h3 class="font-semibold">{{ $usuario->esAdmin() ? 'Actividad del equipo' : 'Su actividad reciente' }}</h3>
                <ol class="mt-4 space-y-4">
                    @forelse ($this->actividad as $a)
                        <li class="flex gap-3">
                            <span class="mt-1 size-2.5 shrink-0 rounded-full tono-{{ $a->accion->tono() }}" style="background: currentColor"></span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm leading-snug">{{ $a->descripcion }}</div>
                                <div class="mt-0.5 text-xs suave">
                                    @if ($usuario->esAdmin()) {{ $a->usuario?->nombreCorto() ?? $a->usuario_nombre }} · @endif
                                    {{ Formato::relativa($a->created_at) }}
                                    @if ($a->referencia && str_starts_with($a->referencia, 'PED-'))
                                        · <a href="{{ route('pedidos.ver', $a->referencia) }}" wire:navigate class="text-marca-600 hover:underline">{{ $a->referencia }}</a>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm suave">Todavía no hay movimientos.</li>
                    @endforelse
                </ol>
                @if ($usuario->esAdmin())
                    <a href="{{ route('admin.trazabilidad') }}" wire:navigate class="btn-fantasma btn btn-chico mt-4 w-full">Ver trazabilidad completa <x-icono nombre="flecha-der" clase="size-3.5" /></a>
                @endif
            </section>
        </div>
    </div>
</div>
