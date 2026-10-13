@php
    use App\Support\Formato;
    use App\Support\Almacenes;
    $p = $pedido;
    $totalCant = $this->lineas->sum(fn ($l) => (float) $l->cantidad);
    $totalDesp = $this->despachado->sum();
    $pctDesp = $totalCant > 0 ? min(100, round($totalDesp / $totalCant * 100)) : 0;
@endphp
<div class="space-y-5" x-data="{ pestana: 'despacho' }">

    {{-- ===== Cabecera ===== --}}
    <div class="tarjeta overflow-hidden">
        <div class="p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                <div class="min-w-0 flex-1">
                    <a href="{{ route('pedidos.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-xs font-medium suave hover:text-marca-600"><x-icono nombre="flecha-izq" clase="size-3.5" /> Pedidos</a>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold tinta">{{ $p->numero }}</h2>
                        <x-estado :valor="$p->estado_despacho" />
                        <x-estado :valor="$p->estado_pago" />
                    </div>
                    <a href="{{ $p->cliente ? route('clientes.ver', $p->cliente) : '#' }}" wire:navigate class="mt-1 inline-flex items-center gap-1.5 text-lg font-medium hover:text-marca-600"><x-icono nombre="tienda" clase="size-4 suave" /> {{ $p->cliente_nombre }}</a>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm suave">
                        <span><x-icono nombre="calendario" clase="mr-1 inline size-4" />{{ Formato::fecha($p->fecha, true) }}</span>
                        <span><x-icono nombre="usuario" clase="mr-1 inline size-4" />{{ $p->vendedor_codigo }} — {{ $p->vendedor_nombre }}{{ $p->cargado_por ? ' · cargó '.$p->cargado_por : '' }}</span>
                        <span><x-icono nombre="billete" clase="mr-1 inline size-4" />{{ $p->forma_pago?->value }}{{ $p->dias_credito ? ' · '.$p->dias_credito.' días · vence '.Formato::fecha($p->fecha_vencimiento) : '' }}</span>
                    </div>
                    @if ($p->fueModificado())
                        <div class="mt-2 text-xs text-amber-700 dark:text-amber-300"><x-icono nombre="lapiz" clase="mr-1 inline size-3.5" />Modificado por {{ $p->modificado_por }} el {{ Formato::fecha($p->fecha_modificacion, true) }}</div>
                    @endif
                </div>
                <div class="grid grid-cols-3 gap-2 lg:w-96">
                    <div class="tarjeta-sec p-3"><div class="text-[11px] suave">Total</div><div class="font-bold tinta numero">{{ Formato::bs($p->total) }}</div></div>
                    <div class="tarjeta-sec p-3"><div class="text-[11px] suave">Cobrado</div><div class="font-bold text-emerald-600 numero">{{ Formato::bs($p->total_pagado) }}</div></div>
                    <div class="tarjeta-sec p-3"><div class="text-[11px] suave">Saldo</div><div class="font-bold numero {{ (float) $p->saldo_pendiente > 0 ? 'text-rojo-600' : 'tinta' }}">{{ Formato::bs($p->saldo_pendiente) }}</div></div>
                </div>
            </div>
            <div class="mt-5"><x-progreso-pedido :estado="$p->estado_despacho" /></div>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-t px-5 py-3 linea fondo-papel-2">
            <div class="flex items-center gap-2 text-sm suave">
                <x-icono nombre="camion" clase="size-4" /> Despachado {{ $pctDesp }}%
                <span class="h-2 w-28 overflow-hidden rounded-full bg-[var(--borde)]"><span class="block h-full rounded-full bg-emerald-500" style="width: {{ $pctDesp }}%"></span></span>
                · {{ Formato::kg($p->pesoTotal()) }}
            </div>
            <div class="ml-auto flex gap-2">
                <button type="button" onclick="window.rastro && window.rastro('ver', 'Imprimió el pedido {{ $p->numero }}', { referencia: '{{ $p->numero }}' }); window.print()" class="btn-fantasma btn btn-chico"><x-icono nombre="imprimir" clase="size-4" /> Imprimir</button>
                @if ($puedeEditar)
                    <button wire:click="abrirEdicion" class="btn-borde btn btn-chico"><x-icono nombre="lapiz" clase="size-4" /> Editar pedido</button>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Pestañas ===== --}}
    <div class="no-imprimir flex gap-1 rounded-2xl p-1 fondo-papel-2 border linea">
        @foreach (['despacho' => ['Proformas y despacho', 'camion'], 'productos' => ['Productos', 'caja'], 'historial' => ['Historial', 'huella']] as $clave => [$texto, $icono])
            <button type="button" @click="pestana = '{{ $clave }}'" class="flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition"
                    :class="pestana === '{{ $clave }}' ? 'bg-[var(--papel)] shadow-sm tinta' : 'suave'">
                <x-icono :nombre="$icono" clase="size-4" /> <span class="hidden sm:inline">{{ $texto }}</span>
            </button>
        @endforeach
    </div>

    {{-- ===== Proformas y despacho ===== --}}
    <div x-show="pestana === 'despacho'" class="grid gap-4 xl:grid-cols-2">
        @foreach ($this->proformas as $pf)
            @php
                $alm = Almacenes::porCodigo($pf->almacen_codigo);
                $cant = $pf->lineas->sum(fn ($l) => (float) $l->cantidad);
                $desp = $pf->lineas->sum(fn ($l) => $this->despachado[$l->id] ?? 0);
                $pct = $cant > 0 ? min(100, round($desp / $cant * 100)) : 0;
                $bloqueada = $pf->notas->contains(fn ($n) => ! $n->anulada);
                $pendientes = $pf->lineas->filter(fn ($l) => (float) $l->cantidad - ($this->despachado[$l->id] ?? 0) > 0.0005)->count();
            @endphp
            <section class="tarjeta overflow-hidden" wire:key="pf-{{ $pf->id }}">
                <div class="h-1.5" style="background: {{ $alm->color }}"></div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold" style="color: {{ $alm->color }}">{{ $pf->almacen }}</div>
                            <div class="text-xs suave">{{ $pf->clave }}</div>
                        </div>
                        <x-estado :valor="$pf->estado" :corto="false" />
                    </div>

                    {{-- Numero de proforma --}}
                    <div class="mt-4">
                        <label class="etiqueta">N° de proforma (ERP)</label>
                        @if ($puedeDespachar && ! $bloqueada)
                            <form wire:submit="numerar({{ $pf->id }})" class="flex gap-2">
                                <input type="text" wire:model="numeros.{{ $pf->id }}" class="campo numero" placeholder="Escriba el número…">
                                <button class="btn-primario btn shrink-0"><x-icono nombre="check" clase="size-4" /> Guardar</button>
                            </form>
                        @else
                            <div class="flex items-center gap-2 rounded-xl border px-3.5 py-2.5 linea fondo-papel-2">
                                @if ($bloqueada) <x-icono nombre="candado" clase="size-4 suave" /> @endif
                                <span class="font-semibold numero">{{ $pf->numero ?: 'Sin número' }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Avance --}}
                    <div class="mt-4">
                        <div class="mb-1 flex justify-between text-xs suave"><span>{{ Formato::bs($pf->total) }} · {{ $pendientes }} producto(s) pendiente(s)</span><span>{{ $pct }}%</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-[var(--borde)]"><div class="h-full rounded-full transition-all" style="width: {{ $pct }}%; background: {{ $pct >= 100 ? '#10b981' : $alm->color }}"></div></div>
                    </div>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($pf->lineas as $l)
                            @php($d = $this->despachado[$l->id] ?? 0)
                            <li class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate">{{ $l->producto_descripcion }}</span>
                                <span class="shrink-0 text-xs numero">
                                    <span @class(['font-semibold', 'text-emerald-600' => $d >= (float) $l->cantidad])>{{ Formato::cantidad($d) }}</span><span class="suave"> / {{ Formato::cantidad($l->cantidad) }} {{ $l->producto_unidad }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($puedeDespachar && $pendientes > 0)
                        <button wire:click="abrirDespacho({{ $pf->id }})" @class(['btn w-full mt-4', 'btn-rojo' => $pf->tieneNumero(), 'btn-borde' => ! $pf->tieneNumero()])>
                            <x-icono nombre="camion" clase="size-4" /> {{ $pf->tieneNumero() ? 'Despachar · crear nota de venta' : 'Cargue el número para despachar' }}
                        </button>
                    @endif

                    {{-- Notas --}}
                    @if ($pf->notas->isNotEmpty())
                        <div class="mt-5 space-y-2">
                            <div class="rotulo">Notas de venta</div>
                            @foreach ($pf->notas as $n)
                                <div @class(['rounded-xl border p-3 linea', 'opacity-70' => $n->anulada]) wire:key="nota-{{ $n->id }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <x-icono nombre="documento" clase="size-4 suave" />
                                            <span @class(['font-semibold', 'line-through' => $n->anulada])>Nota {{ $n->numero }}</span>
                                            @if ($n->anulada) <span class="pastilla tono-rojo">Anulada</span> @else <x-estado :valor="$n->estado_pago" /> @endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            @if ($n->comprobante_ruta || $n->sp_id)
                                                <a href="{{ route('comprobantes.ver', ['nota', $n->id]) }}" target="_blank" class="rounded-lg p-1.5 suave hover:bg-[var(--papel-2)] hover:text-marca-600" title="Ver comprobante"><x-icono nombre="adjunto" clase="size-4" /></a>
                                            @endif
                                            @if ($puedeDespachar && ! $n->anulada && ! $n->cobros_con_pago)
                                                <button wire:click="abrirAnular({{ $n->id }})" class="rounded-lg p-1.5 suave hover:bg-rojo-50 hover:text-rojo-600" title="Anular nota"><x-icono nombre="x-circulo" clase="size-4" /></button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-1 text-xs suave">{{ Formato::fecha($n->fecha, true) }} · {{ Formato::bs($n->total) }}{{ ! $n->anulada ? ' · saldo '.Formato::bs($n->saldo_pendiente) : '' }}{{ $n->creado_por ? ' · '.$n->creado_por : '' }}</div>
                                    @if ($n->anulada)
                                        <div class="mt-2 rounded-lg bg-rojo-50 p-2 text-xs text-rojo-700 dark:bg-rojo-500/10 dark:text-red-300">Motivo: {{ $n->anulada_motivo }}<br>Anulada por {{ $n->anulada_por }} el {{ Formato::fecha($n->anulada_fecha, true) }}</div>
                                    @else
                                        <div class="mt-1.5 text-xs">@foreach ($n->lineas as $nl){{ Formato::cantidad($nl->cantidad) }} {{ $nl->producto_unidad }} · {{ $nl->producto_descripcion }}@if (! $loop->last)<br>@endif @endforeach</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    {{-- ===== Productos ===== --}}
    <div x-show="pestana === 'productos'" x-cloak class="tarjeta overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Producto</th><th>Almacén</th><th class="text-right">Cantidad</th><th class="text-right">Despachado</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr></thead>
            <tbody>
                @foreach ($this->lineas as $l)
                    @php($d = $this->despachado[$l->id] ?? 0)
                    <tr>
                        <td><div class="font-medium">{{ $l->producto_descripcion }}</div><div class="text-xs suave">{{ $l->producto_codigo }}{{ $l->producto_micronaje ? ' · micronaje '.$l->producto_micronaje : '' }}</div></td>
                        <td class="text-xs">{{ $l->almacen }}</td>
                        <td class="text-right numero">{{ Formato::cantidad($l->cantidad) }} {{ $l->producto_unidad }}</td>
                        <td class="text-right numero"><span @class(['text-emerald-600 font-semibold' => $d >= (float) $l->cantidad])>{{ Formato::cantidad($d) }}</span> <span class="suave">· pend. {{ Formato::cantidad(max(0, (float) $l->cantidad - $d)) }}</span></td>
                        <td class="text-right numero">{{ Formato::bs($l->precio_unitario) }}</td>
                        <td class="text-right font-semibold numero">{{ Formato::bs($l->subtotal()) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="5" class="text-right font-semibold">Total</td><td class="text-right text-lg font-bold tinta numero">{{ Formato::bs($p->total) }}</td></tr></tfoot>
        </table>
    </div>

    {{-- ===== Historial (trazabilidad del documento) ===== --}}
    <div x-show="pestana === 'historial'" x-cloak class="tarjeta p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-semibold">Quién hizo qué con este pedido</h3>
            <span class="text-xs suave">{{ $this->historial->count() }} evento(s)</span>
        </div>
        <ol class="relative space-y-5 border-l-2 pl-6 linea">
            @forelse ($this->historial as $h)
                <li class="relative">
                    <span class="absolute top-0.5 -left-[33px] grid size-6 place-items-center rounded-full ring-4 ring-[var(--papel)] tono-{{ $h->accion->tono() }}"><span class="size-2 rounded-full bg-current"></span></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pastilla tono-{{ $h->accion->tono() }}">{{ $h->accion->etiqueta() }}</span>
                        <span class="text-xs suave">{{ Formato::fecha($h->created_at, true) }} · {{ Formato::relativa($h->created_at) }}</span>
                    </div>
                    <div class="mt-1 text-sm">{{ $h->descripcion }}</div>
                    <div class="mt-0.5 text-xs suave"><x-icono nombre="usuario" clase="mr-0.5 inline size-3" />{{ $h->usuario?->name ?? $h->usuario_nombre ?? 'Sistema' }}{{ $h->ip ? ' · '.$h->ip : '' }}</div>
                    @if ($h->correo)
                        <div class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs fondo-papel-2">
                            <x-icono nombre="correo" clase="size-3.5" />
                            @if ($h->correo->estado === 'enviado') <span class="text-emerald-600">Enviado</span> @elseif ($h->correo->estado === 'fallido') <span class="text-rojo-600">No se pudo enviar</span> @else <span class="suave">En cola</span> @endif
                        </div>
                    @endif
                    @if (! empty($h->datos['motivo']))
                        <div class="mt-1.5 rounded-lg p-2 text-xs fondo-papel-2">Motivo: {{ $h->datos['motivo'] }}</div>
                    @endif
                </li>
            @empty
                <li class="text-sm suave">Este pedido llegó desde SharePoint y no tiene eventos registrados en este sistema.</li>
            @endforelse
        </ol>
    </div>

    {{-- ===================== Modal: despacho ===================== --}}
    <x-modal abierto="verDespacho" titulo="Crear nota de venta" ancho="max-w-2xl">
        @if ($proformaDespacho && ($pf = $this->proformas->firstWhere('id', $proformaDespacho)))
            <form wire:submit="despachar" class="space-y-4">
                <div class="flex items-center justify-between rounded-xl p-3 text-sm fondo-papel-2">
                    <span><strong>{{ $pf->almacen }}</strong> · proforma {{ $pf->numero }}</span>
                    <button type="button" wire:click="todoPendiente" class="btn-fantasma btn btn-chico text-marca-600">Todo lo pendiente</button>
                </div>
                <div class="space-y-2">
                    @foreach ($pf->lineas as $l)
                        @php($pend = max(0, (float) $l->cantidad - ($this->despachado[$l->id] ?? 0)))
                        <div class="flex items-center gap-3 rounded-xl border p-3 linea" wire:key="d-{{ $l->id }}">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ $l->producto_descripcion }}</div>
                                <div class="text-xs suave">Pendiente: <strong>{{ Formato::cantidad($pend) }}</strong> {{ $l->producto_unidad }} · {{ Formato::bs($l->precio_unitario) }} c/u</div>
                            </div>
                            <input type="number" step="{{ $l->esPorPieza() ? 1 : 'any' }}" min="0" max="{{ $pend }}" inputmode="decimal" wire:model.live.debounce.300ms="cantidades.{{ $l->id }}" @disabled($pend <= 0) class="campo !w-28 text-right numero" placeholder="0">
                        </div>
                    @endforeach
                    @error('cantidades') <p class="error">{{ $message }}</p> @enderror
                </div>
                @php($totalNota = $pf->lineas->sum(fn ($l) => (float) ($cantidades[$l->id] ?? 0) * (float) $l->precio_unitario))
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="etiqueta">Número de nota de venta</label>
                        <input type="text" wire:model="notaNumero" class="campo numero @error('numero') campo-error @enderror" placeholder="Ej.: 00027520">
                        @error('numero') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col justify-end rounded-xl p-3 text-right fondo-papel-2">
                        <span class="text-xs suave">Total de esta nota</span>
                        <span class="text-xl font-bold tinta numero">{{ Formato::bs($totalNota) }}</span>
                    </div>
                </div>
                <div>
                    <span class="etiqueta">Comprobante de la nota</span>
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed p-4 linea hover:border-marca-400 @error('comprobante') !border-rojo-500 @enderror">
                        <x-icono nombre="adjunto" clase="size-5 text-marca-600" />
                        <span class="flex-1 text-sm">{{ $comprobante ? $comprobante->getClientOriginalName() : 'Adjunte la foto o PDF de la nota (obligatorio)' }}</span>
                        <input type="file" wire:model="comprobante" accept="image/*,application/pdf" class="sr-only">
                    </label>
                    <div wire:loading wire:target="comprobante" class="mt-1 text-xs suave">Subiendo…</div>
                    @error('comprobante') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-borde btn" @click="ver = false">Cancelar</button>
                    <button class="btn-rojo btn" wire:loading.attr="disabled" wire:target="despachar,comprobante"><x-icono nombre="check" clase="size-4" /> Crear nota de venta</button>
                </div>
            </form>
        @endif
    </x-modal>

    {{-- ===================== Modal: anular ===================== --}}
    <x-modal abierto="verAnular" titulo="Anular nota de venta">
        <form wire:submit="anular" class="space-y-4">
            <p class="text-sm suave">Las cantidades de la nota vuelven a quedar pendientes de despacho y se avisa por correo a almacenes y al vendedor. No se puede deshacer.</p>
            <div>
                <label class="etiqueta">Motivo de la anulación</label>
                <textarea wire:model="motivo" rows="3" class="campo @error('motivo') campo-error @enderror" placeholder="Explique el motivo (al menos 10 caracteres)"></textarea>
                @error('motivo') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-borde btn" @click="ver = false">Cancelar</button>
                <button class="btn-rojo btn"><x-icono nombre="x-circulo" clase="size-4" /> Confirmar anulación</button>
            </div>
        </form>
    </x-modal>

    {{-- ===================== Modal: editar ===================== --}}
    <x-modal abierto="verEditar" titulo="Editar {{ $p->numero }}" ancho="max-w-3xl">
        <form wire:submit="guardarEdicion" class="space-y-5">
            @if (collect($editLineas)->contains('bloqueada', true))
                <div class="flex gap-2 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-icono nombre="candado" clase="size-4 shrink-0" /> Los almacenes con candado ya tienen notas de venta: esos productos están bloqueados. Los demás se pueden editar y puede agregar productos de almacenes libres.
                </div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <span class="etiqueta">Forma de pago</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (\App\Enums\FormaPago::cases() as $f)
                            <label @class(['opcion', 'opcion-activa' => $editFormaPago === $f->value])><input type="radio" class="sr-only" wire:model.live="editFormaPago" value="{{ $f->value }}">{{ $f->value }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="etiqueta">Días de crédito @if ($editFormaPago === 'Crédito' && $editDias) <span class="font-normal">· vence {{ $p->fecha->copy()->addDays((int) $editDias)->format('d/m/Y') }}</span> @endif</label>
                    <input type="number" min="1" max="99" wire:model.live="editDias" @disabled($editFormaPago !== 'Crédito') class="campo numero @error('dias_credito') campo-error @enderror">
                    @error('dias_credito') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-2">
                <div class="rotulo">Productos</div>
                @foreach ($editLineas as $i => $l)
                    <div class="flex flex-wrap items-center gap-2 rounded-xl border p-3 linea" wire:key="e-{{ $i }}">
                        <div class="min-w-0 flex-1 basis-48">
                            <div class="truncate text-sm font-medium">@if ($l['bloqueada'])<x-icono nombre="candado" clase="mr-1 inline size-3.5 text-amber-600" />@endif{{ $l['descripcion'] }}</div>
                            <div class="text-xs suave">{{ $l['almacen'] }}{{ $l['id'] ? '' : ' · nuevo' }}</div>
                        </div>
                        <input type="number" step="any" wire:model="editLineas.{{ $i }}.cantidad" @disabled($l['bloqueada']) class="campo !w-24 !py-1.5 text-right numero" placeholder="Cant.">
                        <span class="text-xs suave">{{ $l['unidad'] }} × Bs</span>
                        <input type="number" step="any" wire:model="editLineas.{{ $i }}.precio" @disabled($l['bloqueada']) class="campo !w-24 !py-1.5 text-right numero" placeholder="Precio">
                        @unless ($l['bloqueada'])
                            <button type="button" wire:click="quitarEdicion({{ $i }})" class="rounded-lg p-1.5 suave hover:bg-rojo-50 hover:text-rojo-600"><x-icono nombre="papelera" clase="size-4" /></button>
                        @endunless
                    </div>
                @endforeach
            </div>

            <div class="relative">
                <x-icono nombre="mas" clase="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 suave" />
                <input type="search" wire:model.live.debounce.250ms="editBuscar" class="campo !pl-10" placeholder="Agregar producto: busque por descripción o código…">
                @if ($this->resultadosEdicion->isNotEmpty())
                    <div class="tarjeta absolute inset-x-0 bottom-full z-10 mb-1 p-1.5">
                        @foreach ($this->resultadosEdicion as $r)
                            <button type="button" wire:click="agregarEdicion({{ $r->id }})" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-[var(--papel-2)]">{{ $r->descripcion }} <span class="suave">· {{ $r->codigo }}</span></button>
                        @endforeach
                    </div>
                @endif
            </div>

            @php($nuevoTotal = collect($editLineas)->sum(fn ($l) => (float) $l['cantidad'] * (float) $l['precio']))
            <div class="flex items-center justify-between rounded-xl p-3 fondo-papel-2">
                <span class="text-sm suave">Cobrado {{ Formato::bs($p->total_pagado) }} · saldo {{ Formato::bs(max(0, $nuevoTotal - (float) $p->total_pagado)) }}</span>
                <span class="text-lg font-bold tinta numero">Nuevo total {{ Formato::bs($nuevoTotal) }}</span>
            </div>
            <p class="text-xs suave">Al guardar se envía un correo «Proforma modificada» por cada almacén afectado, con lo que cambió.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-borde btn" @click="ver = false">Cancelar</button>
                <button class="btn-primario btn" wire:loading.attr="disabled"><x-icono nombre="check" clase="size-4" /> Guardar cambios</button>
            </div>
        </form>
    </x-modal>
</div>
