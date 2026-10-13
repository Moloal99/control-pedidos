@php use App\Support\Formato; @endphp
<div class="space-y-5">
    {{-- ===== Visita en curso ===== --}}
    @if ($this->visitaActual)
        @php($v = $this->visitaActual)
        <div class="tarjeta relative overflow-hidden p-5 animate-entrar">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-emerald-500"></div>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15">
                    <x-icono nombre="check-circulo" clase="size-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="rotulo">Visita en curso · {{ Formato::relativa($v->fecha) }}</div>
                    <div class="mt-0.5 truncate text-lg font-semibold">{{ $v->cliente_nombre }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="pastilla tono-azul">{{ $v->tipo_contacto->value }}</span>
                        <span class="pastilla tono-neutro">{{ $v->motivo->value }}</span>
                        @if ($v->forma_pago)
                            <span class="pastilla tono-verde">{{ $v->forma_pago->value }}{{ $v->dias_credito ? ' · '.$v->dias_credito.' días' : '' }}</span>
                        @endif
                        @if ($v->cargado_por)
                            <span class="pastilla tono-ambar">A nombre de {{ $v->vendedor_nombre }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    @if ($v->esPedido())
                        <a href="{{ route('pedido') }}" wire:navigate class="btn-primario btn">
                            <x-icono nombre="carrito" clase="size-4" /> Cargar pedido
                            @if ($carrito) <span class="rounded-full bg-white/25 px-1.5 text-xs">{{ $carrito }}</span> @endif
                        </a>
                    @endif
                    <button type="button" wire:click="nuevaVisita" class="btn-borde btn">
                        <x-icono nombre="mas" clase="size-4" /> Nueva visita
                    </button>
                </div>
            </div>
            @if ($confirmarDescarte)
                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3.5 text-sm text-amber-800 sm:flex-row sm:items-center dark:bg-amber-500/10 dark:text-amber-200">
                    <x-icono nombre="alerta" clase="size-5 shrink-0" />
                    <span class="flex-1">Tiene {{ $carrito }} producto(s) cargados sin guardar. Si continúa, se pierden.</span>
                    <div class="flex gap-2">
                        <a href="{{ route('pedido') }}" wire:navigate class="btn-borde btn btn-chico">Ir a guardarlos</a>
                        <button wire:click="nuevaVisita" class="btn-rojo btn btn-chico">Descartar y seguir</button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
        <form wire:submit="registrar" class="space-y-5">
            {{-- ===== Cliente ===== --}}
            <section class="tarjeta p-5">
                <div class="mb-3 flex items-center gap-2">
                    <span class="grid size-7 place-items-center rounded-lg bg-marca-600 text-xs font-bold text-white">1</span>
                    <h2 class="font-semibold">Cliente</h2>
                </div>

                @if ($this->cliente)
                    <div class="flex items-center gap-3 rounded-xl border border-marca-200 bg-marca-50/60 p-3 dark:border-marca-500/30 dark:bg-marca-500/10">
                        <span class="grid size-10 place-items-center rounded-xl bg-marca-600 text-white"><x-icono nombre="tienda" clase="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $this->cliente->nombre }}</div>
                            <div class="text-xs suave">Código {{ $this->cliente->codigo }}{{ $this->cliente->direccionLegible() ? ' · '.$this->cliente->direccionLegible() : '' }}</div>
                        </div>
                        <button type="button" wire:click="quitarCliente" class="btn-fantasma btn btn-chico">Cambiar</button>
                    </div>
                @else
                    <div class="relative" x-data="{ abierto: true }" @click.outside="abierto = false">
                        <x-icono nombre="lupa" clase="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 suave" />
                        <input type="search" wire:model.live.debounce.250ms="buscarCliente" @focus="abierto = true" placeholder="Busque por nombre o código del cliente…"
                               class="campo !pl-10 @error('cliente_id') campo-error @enderror" autocomplete="off" autofocus>
                        @if ($this->clientes->isNotEmpty())
                            <div x-show="abierto" class="tarjeta absolute inset-x-0 top-full z-20 mt-1.5 overflow-hidden p-1.5 animate-entrar">
                                @foreach ($this->clientes as $c)
                                    <button type="button" wire:click="elegirCliente({{ $c->id }})" wire:key="cli-{{ $c->id }}" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-[var(--papel-2)]">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg fondo-papel-2 suave"><x-icono nombre="tienda" clase="size-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold">{{ $c->nombre }}</span>
                                            <span class="block text-xs suave">{{ $c->codigo }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($buscarCliente)) >= 2)
                            <div class="mt-2 text-sm suave">No se encontró ningún cliente con «{{ $buscarCliente }}».</div>
                        @endif
                    </div>
                    @error('cliente_id') <p class="error">{{ $message }}</p> @enderror
                @endif
            </section>

            {{-- ===== Contacto y motivo ===== --}}
            <section class="tarjeta space-y-5 p-5">
                <div class="flex items-center gap-2">
                    <span class="grid size-7 place-items-center rounded-lg bg-marca-600 text-xs font-bold text-white">2</span>
                    <h2 class="font-semibold">¿Cómo fue el contacto?</h2>
                </div>

                <div>
                    <span class="etiqueta">Tipo de contacto</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (\App\Enums\TipoContacto::cases() as $t)
                            <label @class(['opcion', 'opcion-activa' => $tipo_contacto === $t->value])>
                                <input type="radio" wire:model.live="tipo_contacto" value="{{ $t->value }}" class="sr-only">
                                <x-icono :nombre="$t->icono()" clase="size-4" /> {{ $t->value }}
                            </label>
                        @endforeach
                    </div>
                    @error('tipo_contacto') <p class="error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="etiqueta">Motivo</span>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Enums\MotivoVisita::cases() as $m)
                            <label @class(['opcion !flex-col !py-3.5', 'opcion-activa' => $motivo === $m->value])>
                                <input type="radio" wire:model.live="motivo" value="{{ $m->value }}" class="sr-only">
                                <x-icono :nombre="$m->icono()" clase="size-5" /> {{ $m->value }}
                            </label>
                        @endforeach
                    </div>
                    @error('motivo') <p class="error">{{ $message }}</p> @enderror
                </div>

                @if ($motivo === 'Pedido')
                    <div class="grid gap-4 rounded-2xl p-4 fondo-papel-2 sm:grid-cols-2 animate-entrar">
                        <div>
                            <span class="etiqueta">Forma de pago</span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (\App\Enums\FormaPago::cases() as $f)
                                    <label @class(['opcion', 'opcion-activa' => $forma_pago === $f->value])>
                                        <input type="radio" wire:model.live="forma_pago" value="{{ $f->value }}" class="sr-only"> {{ $f->value }}
                                    </label>
                                @endforeach
                            </div>
                            @error('forma_pago') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        @if ($forma_pago === 'Crédito')
                            <div class="animate-entrar">
                                <span class="etiqueta">Días de crédito</span>
                                <div class="flex gap-1.5">
                                    @foreach ([15, 30, 45, 60] as $d)
                                        <button type="button" wire:click="$set('dias_credito', {{ $d }})" @class(['opcion flex-1 !px-2', 'opcion-activa' => $dias_credito === $d])>{{ $d }}</button>
                                    @endforeach
                                    <input type="number" min="1" max="99" wire:model.live="dias_credito" class="campo !w-20 text-center @error('dias_credito') campo-error @enderror" placeholder="Otro">
                                </div>
                                @if ($dias_credito)
                                    <p class="mt-1.5 text-xs suave">Vence el {{ now()->addDays((int) $dias_credito)->format('d/m/Y') }}</p>
                                @endif
                                @error('dias_credito') <p class="error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            {{-- ===== Comentario ===== --}}
            <section class="tarjeta space-y-4 p-5">
                <div class="flex items-center gap-2">
                    <span class="grid size-7 place-items-center rounded-lg bg-marca-600 text-xs font-bold text-white">3</span>
                    <h2 class="font-semibold">¿Qué pasó en la visita o llamada?</h2>
                </div>
                <div>
                    <textarea wire:model="comentario" rows="4" class="campo @error('comentario') campo-error @enderror"
                              placeholder="Escriba lo relevante de la visita. Si es un pedido, anote aquí la dirección de entrega…"></textarea>
                    @error('comentario') <p class="error">{{ $message }}</p> @enderror
                </div>

                @if ($usuario->puedeElegirVendedor())
                    <div class="rounded-2xl border border-dashed p-4 linea">
                        <label class="etiqueta" for="vendedor">Cargar a nombre de</label>
                        <select id="vendedor" wire:model.live="vendedor_id" class="campo">
                            <option value="{{ $usuario->id }}">Yo mismo · {{ $usuario->codigo }} — {{ $usuario->name }}</option>
                            @foreach ($this->vendedores as $v)
                                <option value="{{ $v->id }}">{{ $v->codigo }} — {{ $v->name }}</option>
                            @endforeach
                        </select>
                        @if ($atribuido->id !== $usuario->id)
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300"><x-icono nombre="alerta" clase="size-3.5" /> La visita y sus pedidos quedarán a nombre de {{ $atribuido->name }}; usted figura como quien los cargó.</p>
                        @endif
                    </div>
                @else
                    <div class="flex items-center gap-2 text-sm suave">
                        <x-icono nombre="usuario" clase="size-4" />
                        @if ($usuario->codigo)
                            Vendedor: <strong class="tinta">{{ $usuario->codigo }} — {{ $usuario->name }}</strong>
                        @else
                            <span class="text-rojo-600">Su usuario no está registrado como vendedor.</span>
                        @endif
                    </div>
                @endif
            </section>

            <button type="submit" class="btn-rojo btn btn-grande w-full" wire:loading.attr="disabled" wire:target="registrar">
                <span wire:loading.remove wire:target="registrar" class="flex items-center gap-2">
                    <x-icono nombre="check" clase="size-5" />
                    {{ $motivo === 'Pedido' ? 'Registrar visita y cargar pedido' : 'Registrar visita' }}
                </span>
                <span wire:loading wire:target="registrar">Guardando…</span>
            </button>
        </form>

        {{-- ===== Ficha rapida del cliente ===== --}}
        <aside class="space-y-4">
            @if ($r = $this->resumenCliente)
                <div class="tarjeta p-5 animate-entrar">
                    <div class="rotulo">Antes de hablar con el cliente</div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="tarjeta-sec p-3">
                            <div class="text-[11px] suave">Saldo por cobrar</div>
                            <div class="font-bold numero {{ $r['saldo'] > 0 ? 'text-rojo-600' : 'tinta' }}">{{ Formato::bs($r['saldo']) }}</div>
                            <div class="text-[11px] suave">{{ $r['notas'] }} nota(s){{ $r['vencidas'] ? ' · '.$r['vencidas'].' vencida(s)' : '' }}</div>
                        </div>
                        <div class="tarjeta-sec p-3">
                            <div class="text-[11px] suave">Compras 90 días</div>
                            <div class="font-bold numero tinta">{{ Formato::compacto($r['pedidos90']) }}</div>
                        </div>
                    </div>
                    @if ($r['vencidas'])
                        <div class="mt-3 flex items-center gap-2 rounded-xl bg-rojo-50 p-2.5 text-xs font-medium text-rojo-700 dark:bg-rojo-500/10 dark:text-red-300">
                            <x-icono nombre="alerta" clase="size-4" /> Tiene notas vencidas: conviene gestionar el cobro.
                        </div>
                    @endif
                    @if ($r['ultimo'])
                        <div class="mt-4">
                            <div class="text-xs font-semibold suave">Último pedido · {{ Formato::relativa($r['ultimo']->fecha) }}</div>
                            <ul class="mt-2 space-y-1.5 text-sm">
                                @foreach ($r['ultimo']->lineas->take(5) as $l)
                                    <li class="flex justify-between gap-2"><span class="truncate">{{ $l->producto_descripcion }}</span><span class="shrink-0 suave numero">{{ Formato::cantidad($l->cantidad) }} {{ $l->producto_unidad }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <a href="{{ route('clientes.ver', $this->cliente) }}" wire:navigate class="btn-fantasma btn btn-chico mt-3 w-full">Ver ficha completa <x-icono nombre="flecha-der" clase="size-3.5" /></a>
                </div>
            @else
                <div class="tarjeta p-5">
                    <x-vacio icono="tienda" titulo="Elija un cliente" texto="Verá su saldo pendiente, notas vencidas y lo que compró la última vez." class="!py-6" />
                </div>
            @endif

            <div class="tarjeta p-5 text-sm">
                <div class="rotulo mb-2">Cómo funciona</div>
                <ol class="space-y-2 suave">
                    <li class="flex gap-2"><span class="font-bold text-marca-600">1.</span> Registre la visita o llamada.</li>
                    <li class="flex gap-2"><span class="font-bold text-marca-600">2.</span> Si es pedido, cargue los productos.</li>
                    <li class="flex gap-2"><span class="font-bold text-marca-600">3.</span> Al guardar, se arma una proforma por almacén y se avisa por correo.</li>
                </ol>
            </div>
        </aside>
    </div>
</div>
