@php use App\Support\Formato; @endphp
<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <x-kpi etiqueta="Por confirmar" :valor="$this->resumen['pendientes']" icono="reloj" tono="ambar" :detalle="Formato::bs($this->resumen['monto']).' esperando aprobación'" />
        <x-kpi etiqueta="Confirmado hoy" :valor="Formato::compacto($this->resumen['hoy'])" icono="check-circulo" tono="verde" />
        <x-kpi etiqueta="Su rol" :valor="$usuario->rol->etiqueta()" icono="escudo" :detalle="$usuario->puedeConfirmarCobranzas() ? 'Puede confirmar y corregir' : 'Solo consulta'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_28rem]">
        <section @class(['space-y-3', 'hidden lg:block' => $this->cobranza])>
            <div class="tarjeta p-3">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div class="flex gap-1 rounded-xl p-1 fondo-papel-2">
                        @foreach (['pendientes' => 'Pendientes', 'confirmadas' => 'Confirmadas', 'todas' => 'Todas'] as $clave => $texto)
                            <button type="button" wire:click="$set('filtro', '{{ $clave }}')" @class(['rounded-lg px-3 py-1.5 text-sm font-semibold', 'bg-[var(--papel)] shadow-sm tinta' => $filtro === $clave, 'suave' => $filtro !== $clave])>{{ $texto }}</button>
                        @endforeach
                    </div>
                    <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Nota, cliente, vendedor o N°" class="campo flex-1">
                </div>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <input type="date" wire:model.live="desde" class="campo" title="Desde">
                    <input type="date" wire:model.live="hasta" class="campo" title="Hasta">
                </div>
            </div>

            <div class="tarjeta divide-y overflow-hidden linea">
                @forelse ($cobranzas as $c)
                    <button type="button" wire:click="elegir({{ $c->id }})" wire:key="c-{{ $c->id }}" @class(['flex w-full items-center gap-3 px-4 py-3.5 text-left linea hover:bg-[var(--papel-2)]', 'bg-marca-50/70 dark:bg-marca-500/10' => $seleccion === $c->id])>
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15' => $c->estaConfirmada(), 'bg-amber-50 text-amber-600 dark:bg-amber-500/15' => ! $c->estaConfirmada()])>
                            <x-icono :nombre="$c->estaConfirmada() ? 'check' : 'reloj'" clase="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">Nota {{ $c->nota_numero }} <span class="text-xs font-normal suave">· {{ Formato::fecha($c->fecha, true) }}</span></span>
                            <span class="block truncate text-sm">{{ $c->cliente_nombre }}</span>
                            <span class="block truncate text-xs suave">{{ $c->vendedor_nombre }}{{ $c->numero ? ' · N° '.$c->numero : '' }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-bold numero">{{ Formato::bs($c->monto) }}</span>
                            <span class="pastilla tono-{{ $c->tipo_pago?->tono() ?? 'neutro' }}">{{ $c->forma_pago?->value }}</span>
                        </span>
                    </button>
                @empty
                    <x-vacio icono="check-circulo" titulo="No hay cobranzas para mostrar" texto="{{ $filtro === 'pendientes' ? 'Todo está confirmado.' : 'Pruebe con otro filtro o rango de fechas.' }}" />
                @endforelse
            </div>
            <div>{{ $cobranzas->links() }}</div>
        </section>

        <section>
            @if ($c = $this->cobranza)
                <div class="tarjeta overflow-hidden lg:sticky lg:top-20 animate-entrar">
                    <div class="flex items-start justify-between border-b p-5 linea">
                        <div>
                            <div class="rotulo">Cobranza · Nota {{ $c->nota_numero }}</div>
                            <div class="mt-1 text-3xl font-bold tinta numero">{{ Formato::bs($c->monto) }}</div>
                            <div class="mt-1 flex flex-wrap gap-1.5"><x-estado :valor="$c->estado" /> <span class="pastilla tono-{{ $c->tipo_pago?->tono() }}">{{ $c->tipo_pago?->value }}</span> <span class="pastilla tono-neutro">{{ $c->forma_pago?->value }}</span></div>
                        </div>
                        <button wire:click="$set('seleccion', null)" class="btn-fantasma btn !p-1.5"><x-icono nombre="x" /></button>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 p-5 text-sm">
                        <div class="col-span-2"><dt class="text-xs suave">Cliente</dt><dd class="font-medium">{{ $c->cliente_nombre }}</dd></div>
                        <div><dt class="text-xs suave">Vendedor</dt><dd class="font-medium">{{ $c->vendedor_nombre }}</dd></div>
                        <div><dt class="text-xs suave">Registró</dt><dd class="font-medium">{{ $c->cargadoPor?->name ?? ($c->cargado_por ?: $c->vendedor_nombre) }}</dd></div>
                        <div><dt class="text-xs suave">Fecha</dt><dd>{{ Formato::fecha($c->fecha, true) }}</dd></div>
                        <div><dt class="text-xs suave">Pedido</dt><dd>@if ($c->pedido_numero)<a href="{{ route('pedidos.ver', $c->pedido_numero) }}" wire:navigate class="text-marca-600 hover:underline">{{ $c->pedido_numero }}</a>@else — @endif</dd></div>
                        <div><dt class="text-xs suave">Saldo antes</dt><dd class="numero">{{ Formato::bs($c->saldo_antes) }}</dd></div>
                        <div><dt class="text-xs suave">Saldo después</dt><dd class="numero">{{ Formato::bs($c->saldo_despues) }}</dd></div>
                        @if ($c->estaConfirmada())
                            <div><dt class="text-xs suave">N° de cobranza</dt><dd class="font-semibold numero">{{ $c->numero }}</dd></div>
                            <div><dt class="text-xs suave">Confirmó</dt><dd>{{ $c->confirmada_por }} · {{ Formato::fecha($c->fecha_confirmacion, true) }}</dd></div>
                            @if ($c->numero_anterior)
                                <div class="col-span-2 rounded-lg p-2 text-xs fondo-papel-2">N° anterior: <span class="line-through">{{ $c->numero_anterior }}</span> · {{ $c->numero_motivo }} ({{ $c->numero_modif_por }})</div>
                            @endif
                        @endif
                    </dl>

                    {{-- Comprobante --}}
                    <div class="px-5 pb-5">
                        @if ($c->comprobante_ruta)
                            @php($url = route('comprobantes.ver', ['cobranza', $c->id]))
                            @if (\Illuminate\Support\Str::endsWith(strtolower((string) $c->comprobante_nombre), ['.jpg', '.jpeg', '.png', '.webp']))
                                <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt="Comprobante" class="max-h-72 w-full rounded-xl border object-contain linea fondo-papel-2"></a>
                            @else
                                <a href="{{ $url }}" target="_blank" class="btn-borde btn w-full"><x-icono nombre="adjunto" clase="size-4" /> Ver comprobante</a>
                            @endif
                        @elseif ($c->sp_id && $c->forma_pago?->exigeComprobante())
                            <a href="{{ route('comprobantes.ver', ['cobranza', $c->id]) }}" target="_blank" class="btn-borde btn w-full"><x-icono nombre="adjunto" clase="size-4" /> Ver comprobante en SharePoint</a>
                        @else
                            <div class="rounded-xl p-3 text-center text-sm suave fondo-papel-2">Sin comprobante adjunto{{ $c->forma_pago?->value === 'Efectivo' ? ' (pago en efectivo)' : '' }}</div>
                        @endif
                    </div>

                    @if ($usuario->puedeConfirmarCobranzas())
                        <div class="border-t p-5 linea fondo-papel-2">
                            @if (! $c->estaConfirmada())
                                <form wire:submit="confirmar" class="space-y-3">
                                    <label class="etiqueta">N° de nota de cobranza (ERP)</label>
                                    <input type="text" wire:model.live="numero" class="campo text-lg font-semibold uppercase numero @error('numero') campo-error @enderror" placeholder="Ej.: B00012345 o V00012345" autofocus>
                                    @error('numero') <p class="error">{{ $message }}</p> @enderror
                                    <button class="btn-verde btn btn-grande w-full" @disabled(mb_strlen(trim($numero)) < 3) wire:loading.attr="disabled">
                                        <x-icono nombre="check-circulo" clase="size-5" /> Confirmar cobro
                                    </button>
                                    <p class="text-center text-xs suave">Al confirmar se descuenta {{ Formato::bs($c->monto) }} del saldo de la nota y se avisa al vendedor.</p>
                                </form>
                            @else
                                <button wire:click="abrirCorregir" class="btn-borde btn w-full"><x-icono nombre="lapiz" clase="size-4" /> Corregir N° de cobranza</button>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="tarjeta hidden lg:block"><x-vacio icono="recibo" titulo="Elija una cobranza" texto="Revise el comprobante y confirme el cobro con el número que dio el sistema." /></div>
            @endif
        </section>
    </div>

    <x-modal abierto="verCorregir" titulo="Corregir N° de cobranza">
        <form wire:submit="corregir" class="space-y-4">
            <p class="text-sm suave">Número actual: <strong class="tinta">{{ $this->cobranza?->numero }}</strong>. El monto y el saldo de la nota no cambian.</p>
            <div>
                <label class="etiqueta">Número correcto</label>
                <input type="text" wire:model="numeroNuevo" class="campo uppercase numero @error('numero') campo-error @enderror">
                @error('numero') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="etiqueta">Motivo de la corrección</label>
                <textarea wire:model="motivo" rows="3" class="campo @error('motivo') campo-error @enderror" placeholder="Mínimo 10 caracteres"></textarea>
                @error('motivo') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-borde btn" @click="ver = false">Cancelar</button>
                <button class="btn-primario btn">Guardar corrección</button>
            </div>
        </form>
    </x-modal>
</div>
