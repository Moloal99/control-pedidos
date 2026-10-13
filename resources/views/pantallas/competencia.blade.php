@php use App\Support\Formato; @endphp
<div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
    <form wire:submit="guardar" class="tarjeta space-y-5 p-5">
        <div>
            <h2 class="font-semibold">Relevamiento de competencia</h2>
            <p class="text-sm suave">Anote lo que ofrece la competencia en este cliente. Cliente y competidor se mantienen para cargar varios productos seguidos.</p>
        </div>

        {{-- Cliente --}}
        <div>
            <span class="etiqueta">Cliente</span>
            @if ($this->cliente)
                <div class="flex items-center gap-3 rounded-xl border p-3 linea fondo-papel-2">
                    <x-icono nombre="tienda" clase="size-5 text-marca-600" />
                    <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $this->cliente->nombre }}</div><div class="text-xs suave">{{ $this->cliente->codigo }}</div></div>
                    <button type="button" wire:click="$set('cliente_id', null)" class="btn-fantasma btn btn-chico">Cambiar</button>
                </div>
            @else
                <div class="relative">
                    <input type="search" wire:model.live.debounce.250ms="buscarCliente" class="campo @error('cliente_id') campo-error @enderror" placeholder="Busque por nombre o código…">
                    @if ($this->clientes->isNotEmpty())
                        <div class="tarjeta absolute inset-x-0 top-full z-20 mt-1 p-1.5">
                            @foreach ($this->clientes as $c)
                                <button type="button" wire:click="elegirCliente({{ $c->id }})" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-[var(--papel-2)]"><span class="font-medium">{{ $c->nombre }}</span> <span class="suave">· {{ $c->codigo }}</span></button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('cliente_id') <p class="error">{{ $message }}</p> @enderror
            @endif
        </div>

        <div>
            <label class="etiqueta">Competidor</label>
            <input type="text" list="competidores" wire:model="competidor" class="campo @error('competidor') campo-error @enderror" placeholder="Nombre de la marca o empresa">
            <datalist id="competidores">@foreach ($this->competidores as $c)<option value="{{ $c }}">@endforeach</datalist>
            @error('competidor') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="etiqueta">Tipo de producto</span>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach (\App\Enums\TipoCompetencia::cases() as $t)
                    <label @class(['opcion !px-2', 'opcion-activa' => $tipo === $t->value])><input type="radio" class="sr-only" wire:model.live="tipo" value="{{ $t->value }}">{{ $t->value }}</label>
                @endforeach
            </div>
            @error('tipo') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_10rem]">
            <div>
                <label class="etiqueta">Descripción</label>
                <input type="text" wire:model.live.debounce.500ms="descripcion" class="campo @error('descripcion') campo-error @enderror" placeholder="Lo que dice el envase">
                @error('descripcion') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="etiqueta">Medida (opcional)</label>
                <input type="text" wire:model="medida" class="campo" placeholder="90 X 110">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="etiqueta">Unidad</label>
                <select wire:model="unidad" class="campo @error('unidad') campo-error @enderror">
                    <option value="">Elija…</option>
                    @foreach ($this->unidades as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach
                </select>
                @error('unidad') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="etiqueta">Precio Bs</label>
                <input type="number" step="0.01" min="0" inputmode="decimal" wire:model="precio" class="campo numero @error('precio') campo-error @enderror" placeholder="0.00">
                @error('precio') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="etiqueta">Cantidad</label>
                <input type="number" step="any" min="0" inputmode="decimal" wire:model="cantidad" class="campo numero @error('cantidad') campo-error @enderror" placeholder="0">
                @error('cantidad') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="etiqueta">Observación (opcional)</label>
            <textarea wire:model="observacion" rows="2" class="campo" placeholder="Condiciones, entrega, calidad…"></textarea>
        </div>

        <button class="btn-rojo btn btn-grande w-full" wire:loading.attr="disabled"><x-icono nombre="check" clase="size-5" /> Guardar relevamiento</button>
    </form>

    <aside class="space-y-4">
        <div class="tarjeta overflow-hidden">
            <div class="border-b px-5 py-3.5 linea"><h3 class="font-semibold">Cargados hoy <span class="suave font-normal">· {{ $this->hoy->count() }}</span></h3></div>
            <div class="divide-y linea">
                @forelse ($this->hoy as $r)
                    <div class="flex items-start gap-3 px-5 py-3 linea" wire:key="rel-{{ $r->id }}">
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="font-medium">{{ $r->competidor }} · {{ $r->descripcion }} {{ $r->medida }}</div>
                            <div class="text-xs suave">{{ Formato::bs($r->precio) }} · {{ Formato::cantidad($r->cantidad) }} {{ $r->unidad }} · {{ $r->cliente_nombre }}</div>
                        </div>
                        <button wire:click="eliminar({{ $r->id }})" wire:confirm="¿Eliminar este relevamiento?" class="rounded-lg p-1 suave hover:bg-rojo-50 hover:text-rojo-600"><x-icono nombre="papelera" clase="size-4" /></button>
                    </div>
                @empty
                    <x-vacio icono="etiqueta" titulo="Todavía no cargó ningún relevamiento hoy" class="!py-8" />
                @endforelse
            </div>
        </div>

        @if ($this->referencias->isNotEmpty())
            <div class="tarjeta p-5 animate-entrar">
                <div class="rotulo mb-2">Precios relevados antes</div>
                <ul class="space-y-2 text-sm">
                    @foreach ($this->referencias as $r)
                        <li class="flex justify-between gap-2"><span class="truncate">{{ $r->competidor }} · {{ $r->descripcion }}</span><span class="shrink-0 font-semibold numero">{{ Formato::bs($r->precio) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>
