@php use App\Support\Formato; @endphp
<div class="space-y-5" x-data @enfocar-cantidad.window="$nextTick(() => $refs.cantidad?.focus())" @enfocar-busqueda.window="$nextTick(() => $refs.busqueda?.focus())">

    @unless ($this->visita)
        <div class="tarjeta">
            <x-vacio icono="ubicacion" titulo="Primero registre la visita" texto="El pedido se carga sobre una visita o llamada con motivo «Pedido» y su forma de pago.">
                <a href="{{ route('visita') }}" wire:navigate class="btn-primario btn mt-4"><x-icono nombre="ubicacion" clase="size-4" /> Ir a Visita</a>
            </x-vacio>
        </div>
    @else
        @php($v = $this->visita)
        {{-- ===== Cabecera de la venta ===== --}}
        <div class="tarjeta flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-marca-50 text-marca-600 dark:bg-marca-500/15 dark:text-marca-200"><x-icono nombre="tienda" /></span>
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold">{{ $v->cliente_nombre }}</div>
                <div class="flex flex-wrap gap-1.5 pt-1 text-xs">
                    <span class="pastilla tono-neutro">{{ $v->cliente_codigo }}</span>
                    @if ($v->forma_pago)
                        <span class="pastilla tono-verde">{{ $v->forma_pago->value }}{{ $v->dias_credito ? ' · '.$v->dias_credito.' días' : '' }}</span>
                    @else
                        <span class="pastilla tono-rojo">Sin forma de pago</span>
                    @endif
                    <span class="pastilla tono-azul">{{ $v->vendedor_codigo }} — {{ $v->vendedor_nombre }}</span>
                </div>
            </div>
            @if ($atribuido->id !== $usuario->id)
                <div class="flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-icono nombre="alerta" clase="size-4" /> Está cargando a nombre de {{ $atribuido->name }}
                </div>
            @endif
        </div>

        @if (! $v->forma_pago)
            <div class="flex items-center gap-3 rounded-2xl border border-rojo-100 bg-rojo-50 p-4 text-sm text-rojo-700 dark:bg-rojo-500/10 dark:text-red-300">
                <x-icono nombre="alerta" clase="size-5" />
                <span class="flex-1">Esta visita no es de motivo «Pedido»: registre una nueva visita con forma de pago para poder guardar.</span>
                <a href="{{ route('visita') }}" wire:navigate class="btn-borde btn btn-chico">Ir a Visita</a>
            </div>
        @endif

        @if ($guardado)
            {{-- ===== Pedido guardado ===== --}}
            <div class="tarjeta overflow-hidden animate-entrar">
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 p-6 text-white">
                    <x-icono nombre="check-circulo" clase="size-10" />
                    <div class="mt-3 text-xl font-bold">Pedido guardado</div>
                    <div class="mt-1 text-white/85">{{ $guardado['numero'] }} · {{ $guardado['lineas'] }} productos · {{ Formato::bs($guardado['total']) }}</div>
                </div>
                <div class="flex flex-col gap-2 p-4 sm:flex-row">
                    <a href="{{ route('pedidos.ver', $guardado['numero']) }}" wire:navigate class="btn-primario btn flex-1"><x-icono nombre="ojo" clase="size-4" /> Ver el pedido</a>
                    <button wire:click="otroPedido" class="btn-borde btn flex-1"><x-icono nombre="mas" clase="size-4" /> Otro pedido en esta visita</button>
                    <a href="{{ route('visita') }}" wire:navigate class="btn-fantasma btn flex-1">Terminar la visita</a>
                </div>
            </div>
        @else
            <div class="grid gap-5 lg:grid-cols-[1fr_24rem]">
                {{-- ===== Agregar producto ===== --}}
                <div class="space-y-5">
                    <section class="tarjeta p-5">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h2 class="font-semibold">Agregar producto</h2>
                            @if ($this->ultimoPedido && ! empty($this->ultimoPedido->lineas))
                                <button type="button" wire:click="repetirUltimo" class="btn-fantasma btn btn-chico text-marca-600 dark:text-marca-200">
                                    <x-icono nombre="repetir" clase="size-4" /> Repetir último pedido
                                </button>
                            @endif
                        </div>

                        @if ($this->producto)
                            @php($p = $this->producto)
                            @php($alm = $almacenDe($p))
                            <div class="rounded-2xl border p-4 linea animate-entrar">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 size-3 shrink-0 rounded-full" style="background: {{ $alm->color }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold">{{ $p->descripcion }}</div>
                                        <div class="text-xs suave">{{ $p->codigo }} · {{ $alm->nombre }} · se vende en {{ $p->unidadNormalizada() }}</div>
                                    </div>
                                    <button type="button" wire:click="$set('producto_id', null)" class="btn-fantasma btn !p-1"><x-icono nombre="x" clase="size-4" /></button>
                                </div>

                                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs sm:grid-cols-6">
                                    @foreach ([['Bolsas/paq.', $p->bolsas_por_paquete], ['Paq./jaba', $p->paquetes_por_jaba], ['Jabitas/jaba', $p->jabitas_por_jaba], ['Jabas/bulto', $p->jabas_por_bulto], ['Peso prom.', $p->peso_promedio !== null ? Formato::cantidad($p->peso_promedio).' kg' : null], ['Micronaje', $p->micronaje]] as [$e, $valor])
                                        <div class="tarjeta-sec px-1 py-2">
                                            <dt class="suave">{{ $e }}</dt>
                                            <dd class="mt-0.5 font-semibold numero">{{ $valor !== null && $valor !== '' ? (is_numeric($valor) ? Formato::cantidad($valor) : $valor) : '—' }}</dd>
                                        </div>
                                    @endforeach
                                </dl>

                                <form wire:submit="agregar" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                    <div>
                                        <label class="etiqueta">Cantidad en {{ $p->unidadNormalizada() }}</label>
                                        <input x-ref="cantidad" type="number" step="{{ $p->esPorPieza() ? 1 : 'any' }}" min="0" inputmode="decimal" wire:model="cantidad" class="campo numero @error('cantidad') campo-error @enderror" placeholder="0">
                                    </div>
                                    <div>
                                        <label class="etiqueta">Precio unitario (Bs)</label>
                                        <input type="number" step="any" min="0" inputmode="decimal" wire:model="precio" class="campo numero @error('precio') campo-error @enderror" placeholder="0.00">
                                        @if ($this->ultimoPrecio !== null)
                                            <p class="mt-1 text-[11px] suave">Último precio a este cliente: {{ Formato::bs($this->ultimoPrecio) }}</p>
                                        @endif
                                    </div>
                                    <button class="btn-primario btn col-span-2 self-end sm:col-span-1"><x-icono nombre="mas" clase="size-4" /> Agregar</button>
                                </form>
                                @error('cantidad') <p class="error">{{ $message }}</p> @enderror
                                @error('precio') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div class="relative">
                                <x-icono nombre="lupa" clase="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 suave" />
                                <input x-ref="busqueda" type="search" wire:model.live.debounce.250ms="buscar" placeholder="Busque por descripción o código del producto…" class="campo !pl-10" autocomplete="off" autofocus>
                            </div>
                            @if ($this->resultados->isNotEmpty())
                                <div class="mt-2 divide-y overflow-hidden rounded-xl border linea">
                                    @foreach ($this->resultados as $r)
                                        @php($alm = $almacenDe($r))
                                        <button type="button" wire:click="elegir({{ $r->id }})" wire:key="prod-{{ $r->id }}" class="flex w-full items-center gap-3 px-3.5 py-3 text-left linea hover:bg-[var(--papel-2)]">
                                            <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $alm->color }}"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium">{{ $r->descripcion }}</span>
                                                <span class="block text-xs suave">{{ $r->codigo }} · {{ $alm->area }}</span>
                                            </span>
                                            <span class="pastilla tono-neutro">{{ $r->unidadNormalizada() ?: '—' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (mb_strlen(trim($buscar)) >= 2)
                                <p class="mt-2 text-sm suave">Sin resultados para «{{ $buscar }}».</p>
                            @endif
                        @endif
                    </section>

                    @if ($this->ultimoPedido && $lineas === 0)
                        <section class="tarjeta p-5">
                            <div class="rotulo">Último pedido de este cliente · {{ Formato::relativa($this->ultimoPedido->fecha) }}</div>
                            <ul class="mt-3 divide-y text-sm linea">
                                @foreach ($this->ultimoPedido->lineas as $l)
                                    <li class="flex items-center justify-between gap-3 py-2 linea">
                                        <span class="truncate">{{ $l->producto_descripcion }}</span>
                                        <span class="shrink-0 text-right numero"><span class="suave">{{ Formato::cantidad($l->cantidad) }} {{ $l->producto_unidad }} ×</span> {{ Formato::bs($l->precio_unitario) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>

                {{-- ===== Carrito ===== --}}
                <aside class="lg:sticky lg:top-20 lg:self-start">
                    <section class="tarjeta overflow-hidden">
                        <div class="flex items-center justify-between border-b px-5 py-4 linea">
                            <h2 class="font-semibold">Pedido <span class="suave font-normal">· {{ $lineas }} producto(s)</span></h2>
                            @if ($lineas)
                                <button wire:click="vaciar" wire:confirm="¿Quitar todos los productos del pedido?" class="btn-fantasma btn btn-chico">Vaciar</button>
                            @endif
                        </div>

                        @forelse ($this->grupos as $codigo => $grupo)
                            <div wire:key="grupo-{{ $codigo }}">
                                <div class="flex items-center justify-between px-5 pt-4 pb-1.5 text-xs font-semibold" style="color: {{ $grupo['almacen']->color }}">
                                    <span class="flex items-center gap-1.5"><span class="size-2 rounded-full" style="background: {{ $grupo['almacen']->color }}"></span>{{ $grupo['almacen']->nombre }}</span>
                                    <span class="numero">{{ Formato::kg($grupo['kg']) }}</span>
                                </div>
                                @foreach ($grupo['items'] as $item)
                                    <div class="group px-5 py-2.5" wire:key="item-{{ $item['indice'] }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0 text-sm font-medium leading-snug">{{ $item['producto']->descripcion }}</div>
                                            <button wire:click="quitar({{ $item['indice'] }})" class="shrink-0 rounded-lg p-1 suave hover:bg-rojo-50 hover:text-rojo-600" aria-label="Quitar"><x-icono nombre="papelera" clase="size-4" /></button>
                                        </div>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <input type="number" step="any" value="{{ $item['cantidad'] }}" wire:change="actualizar({{ $item['indice'] }}, 'cantidad', $event.target.value)" class="campo !w-24 !px-2 !py-1.5 text-right numero">
                                            <span class="text-xs suave">{{ $item['producto']->unidadNormalizada() }} ×</span>
                                            <input type="number" step="any" value="{{ $item['precio'] }}" wire:change="actualizar({{ $item['indice'] }}, 'precio', $event.target.value)" class="campo !w-24 !px-2 !py-1.5 text-right numero">
                                            <span class="ml-auto text-sm font-semibold numero">{{ Formato::bs($item['cantidad'] * $item['precio']) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <x-vacio icono="carrito" titulo="Todavía no hay productos" texto="Busque un producto y agréguelo al pedido." class="!py-10" />
                        @endforelse

                        @if ($lineas)
                            <div class="mt-3 space-y-1 border-t px-5 py-4 linea fondo-papel-2">
                                <div class="flex justify-between text-sm suave"><span>Peso estimado</span><span class="numero">{{ Formato::kg($kg) }}</span></div>
                                <div class="flex justify-between text-sm suave"><span>Proformas a generar</span><span>{{ count($this->grupos) }}</span></div>
                                <div class="flex items-end justify-between pt-1"><span class="font-semibold">Total</span><span class="text-2xl font-bold tinta numero">{{ Formato::bs($total) }}</span></div>
                            </div>
                            <div class="p-4">
                                <button wire:click="guardar" wire:loading.attr="disabled" @disabled(! $v->forma_pago) class="btn-rojo btn btn-grande w-full">
                                    <span wire:loading.remove wire:target="guardar" class="flex items-center gap-2"><x-icono nombre="check" clase="size-5" /> Guardar pedido</span>
                                    <span wire:loading wire:target="guardar">Guardando en SharePoint…</span>
                                </button>
                            </div>
                        @endif
                    </section>
                </aside>
            </div>
        @endif
    @endunless
</div>
