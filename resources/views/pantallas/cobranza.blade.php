@php use App\Support\Formato; @endphp
<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_26rem]">
    {{-- ===== Notas con saldo ===== --}}
    <section @class(['space-y-4', 'hidden lg:block' => $this->nota])>
        <div class="tarjeta p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <x-icono nombre="lupa" clase="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 suave" />
                    <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por nota, cliente o vendedor" class="campo !pl-10">
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium">
                    <input type="checkbox" wire:model.live="soloVencidas" class="size-4 rounded accent-rojo-600"> Solo vencidas
                </label>
            </div>
        </div>

        <div class="tarjeta overflow-hidden">
            <div class="flex items-center justify-between border-b px-5 py-3.5 linea">
                <h2 class="font-semibold">Notas con saldo <span class="suave font-normal">· {{ $this->notas->count() }}</span></h2>
                <span class="text-xs whitespace-nowrap suave">{{ $usuario->veTodasLasNotas() ? 'Todos los vendedores' : 'Solo sus notas' }}</span>
            </div>
            <div class="divide-y linea">
                @forelse ($this->notas as $n)
                    @php($pendiente = (float) $n->por_confirmar)
                    <button type="button" wire:click="elegir({{ $n->id }})" wire:key="n-{{ $n->id }}" @class(['flex w-full items-center gap-3 px-4 py-3.5 text-left transition sm:gap-4 sm:px-5 linea hover:bg-[var(--papel-2)]', 'bg-marca-50/70 dark:bg-marca-500/10' => $nota_id === $n->id])>
                        <span @class(['hidden size-10 shrink-0 place-items-center rounded-xl sm:grid', 'bg-rojo-50 text-rojo-600 dark:bg-rojo-500/15' => $n->estaVencida(), 'fondo-papel-2 suave' => ! $n->estaVencida()])>
                            <x-icono nombre="documento" clase="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="font-semibold whitespace-nowrap">Nota {{ $n->numero }}</span>
                                <span class="text-xs whitespace-nowrap suave">{{ $n->fecha->format('d/m') }}</span>
                                @if ($n->estaVencida()) <span class="pastilla tono-rojo">Vencida</span> @endif
                            </span>
                            <span class="block truncate text-sm">{{ $n->cliente_nombre }}</span>
                            @if ($usuario->veTodasLasNotas()) <span class="block truncate text-xs suave">{{ $n->vendedor_nombre }}</span> @endif
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block text-xs suave numero">{{ Formato::bs($n->total) }}</span>
                            <span @class(['block font-semibold whitespace-nowrap numero', 'text-amber-600' => $pendiente > 0])>
                                {{ Formato::bs(max(0, $n->saldo_pendiente - $pendiente)) }} @if ($pendiente > 0) <x-icono nombre="reloj" clase="inline size-3.5" /> @endif
                            </span>
                            <span class="block text-[11px] suave">disponible</span>
                        </span>
                    </button>
                @empty
                    <x-vacio icono="check-circulo" titulo="No hay notas de venta con saldo pendiente" />
                @endforelse
            </div>
        </div>

        @if ($this->recientes->isNotEmpty())
            <div class="tarjeta p-5">
                <div class="rotulo mb-3">Sus últimas gestiones</div>
                <ul class="space-y-2.5">
                    @foreach ($this->recientes as $c)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="font-medium">Nota {{ $c->nota_numero }}</span>
                                <span class="suave"> · {{ $c->cliente_nombre }}</span>
                                <span class="block text-xs suave">{{ Formato::relativa($c->fecha) }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="font-semibold numero">{{ $c->hubo_pago ? Formato::bs($c->monto) : 'Sin pago' }}</span>
                                @if ($c->hubo_pago) <x-estado :valor="$c->estado" /> @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- ===== Detalle y cobro ===== --}}
    <section>
        @if ($n = $this->nota)
            <div class="tarjeta overflow-hidden lg:sticky lg:top-20 animate-entrar">
                <div class="bg-gradient-to-br from-marca-600 to-marca-800 p-5 text-white">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs text-white/70">Nota de venta</div>
                            <div class="text-xl font-bold">{{ $n->numero }}</div>
                            <div class="mt-0.5 text-sm text-white/85">{{ $n->cliente_nombre }}</div>
                        </div>
                        <button wire:click="cerrar" class="rounded-lg p-1.5 text-white/80 hover:bg-white/10"><x-icono nombre="x" /></button>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-white/10 p-2"><div class="text-[11px] text-white/70">Total</div><div class="text-sm font-semibold whitespace-nowrap numero sm:text-base">{{ Formato::bs($n->total) }}</div></div>
                        <div class="rounded-xl bg-white/10 p-2"><div class="text-[11px] text-white/70">Saldo</div><div class="text-sm font-semibold whitespace-nowrap numero sm:text-base">{{ Formato::bs($n->saldo_pendiente) }}</div></div>
                        <div class="rounded-xl bg-white/20 p-2"><div class="text-[11px] text-white/80">Disponible</div><div class="text-sm font-bold whitespace-nowrap numero sm:text-base">{{ Formato::bs($this->disponible) }}</div></div>
                    </div>
                    @if ($n->fecha_vencimiento)
                        <div class="mt-3 text-xs text-white/80">Vence el {{ $n->fecha_vencimiento->format('d/m/Y') }} · {{ $n->forma_pago?->value }}</div>
                    @endif
                </div>

                <details class="border-b linea">
                    <summary class="cursor-pointer px-5 py-3 text-sm font-medium">Ver productos ({{ $n->lineas->count() }})</summary>
                    <ul class="space-y-1.5 px-5 pb-4 text-sm">
                        @foreach ($n->lineas as $l)
                            <li class="flex justify-between gap-2"><span class="truncate">{{ $l->producto_descripcion }}</span><span class="shrink-0 suave numero">{{ Formato::cantidad($l->cantidad) }} {{ $l->producto_unidad }} · {{ Formato::bs($l->subtotal()) }}</span></li>
                        @endforeach
                    </ul>
                </details>

                <form wire:submit="guardar" class="space-y-4 p-5">
                    <div>
                        <span class="etiqueta">¿Hubo pago?</span>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="$set('hubo_pago', true)" @class(['opcion', 'opcion-activa' => $hubo_pago === true])><x-icono nombre="check" clase="size-4" /> Sí</button>
                            <button type="button" wire:click="$set('hubo_pago', false)" @class(['opcion', 'opcion-activa' => $hubo_pago === false])><x-icono nombre="x" clase="size-4" /> No</button>
                        </div>
                    </div>

                    @if ($hubo_pago === true)
                        <div class="space-y-4 animate-entrar">
                            <div>
                                <span class="etiqueta">Tipo de pago</span>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach (\App\Enums\TipoPagoCobranza::cases() as $t)
                                        <label @class(['opcion !px-2', 'opcion-activa' => $tipo_pago === $t->value])><input type="radio" class="sr-only" wire:model.live="tipo_pago" value="{{ $t->value }}">{{ $t->value }}</label>
                                    @endforeach
                                </div>
                                @error('tipo_pago') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            @if ($tipo_pago)
                                <div>
                                    <label class="etiqueta">Monto cobrado · máximo {{ Formato::bs($this->disponible) }}</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm font-semibold suave">Bs</span>
                                        <input type="number" step="0.01" min="0" inputmode="decimal" wire:model="monto" @readonly($tipo_pago === 'Completo') class="campo !pl-10 text-lg font-semibold numero @error('monto') campo-error @enderror" placeholder="0.00">
                                    </div>
                                    @error('monto') <p class="error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                            <div>
                                <span class="etiqueta">Forma de pago</span>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach (\App\Enums\FormaPagoCobranza::cases() as $f)
                                        <label @class(['opcion !px-2 text-center !text-xs sm:!text-sm', 'opcion-activa' => $forma_pago === $f->value])><input type="radio" class="sr-only" wire:model.live="forma_pago" value="{{ $f->value }}">{{ $f === \App\Enums\FormaPagoCobranza::Deposito ? 'Depósito' : $f->value }}</label>
                                    @endforeach
                                </div>
                                @error('forma_pago') <p class="error">{{ $message }}</p> @enderror
                            </div>
                            @if ($forma_pago && $forma_pago !== 'Efectivo')
                                <div class="animate-entrar">
                                    <span class="etiqueta">Comprobante</span>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed p-4 transition linea hover:border-marca-400 @error('comprobante') !border-rojo-500 @enderror">
                                        <span class="grid size-10 place-items-center rounded-xl bg-marca-50 text-marca-600 dark:bg-marca-500/15"><x-icono nombre="camara" /></span>
                                        <span class="min-w-0 flex-1 text-sm">
                                            @if ($comprobante)
                                                <span class="block truncate font-medium">{{ $comprobante->getClientOriginalName() }}</span>
                                                <span class="text-xs text-emerald-600">Listo para adjuntar</span>
                                            @else
                                                <span class="block font-medium">Tome una foto o elija el archivo</span>
                                                <span class="text-xs suave">PDF o imagen, hasta 50 MB</span>
                                            @endif
                                        </span>
                                        <input type="file" wire:model="comprobante" accept="image/*,application/pdf" capture="environment" class="sr-only">
                                    </label>
                                    <div wire:loading wire:target="comprobante" class="mt-1 text-xs suave">Subiendo…</div>
                                    @error('comprobante') <p class="error">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($hubo_pago === false || in_array($tipo_pago, ['Parcial', 'Anticipo'], true))
                        <div class="animate-entrar">
                            <label class="etiqueta">Fecha del próximo pago {{ $hubo_pago ? '(opcional)' : '' }}</label>
                            <input type="date" wire:model="fecha_proximo_pago" min="{{ now()->toDateString() }}" class="campo @error('fecha_proximo_pago') campo-error @enderror">
                            @error('fecha_proximo_pago') <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($hubo_pago !== null)
                        <button class="btn-rojo btn btn-grande w-full" wire:loading.attr="disabled" wire:target="guardar,comprobante">
                            <span wire:loading.remove wire:target="guardar" class="flex items-center gap-2"><x-icono nombre="check" clase="size-5" /> {{ $hubo_pago ? 'Guardar cobranza' : 'Registrar gestión sin pago' }}</span>
                            <span wire:loading wire:target="guardar">Guardando…</span>
                        </button>
                        @if ($hubo_pago)
                            <p class="text-center text-xs suave">El saldo de la nota baja cuando Cobranzas confirme el cobro.</p>
                        @endif
                    @endif
                </form>
            </div>
        @else
            <div class="tarjeta hidden lg:block">
                <x-vacio icono="billete" titulo="Elija una nota de venta" texto="Verá su detalle, el saldo disponible para cobrar y podrá registrar el pago con su comprobante." />
            </div>
        @endif
    </section>
</div>
