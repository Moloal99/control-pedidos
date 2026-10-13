@php use App\Support\Formato; $c = $cliente; $saldo = $this->notas->sum('saldo_pendiente'); $vencido = $this->notas->filter->estaVencida()->sum('saldo_pendiente'); @endphp
<div class="space-y-5">
    <section class="tarjeta p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-marca-500 to-marca-700 text-white shadow-lg"><x-icono nombre="tienda" clase="size-7" /></span>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-xl font-bold tinta">{{ $c->nombre }}</h2>
                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm suave">
                    <span>Código {{ $c->codigo }}</span>
                    @if ($c->direccionLegible())<span><x-icono nombre="ubicacion" clase="mr-1 inline size-4" />{{ $c->direccionLegible() }}</span>@endif
                    @if ($c->telefonoLegible())<a href="tel:{{ $c->telefonoLegible() }}" class="hover:text-marca-600"><x-icono nombre="telefono" clase="mr-1 inline size-4" />{{ $c->telefonoLegible() }}</a>@endif
                </div>
            </div>
            @if ($usuario->puedeVender())
                <a href="{{ route('visita', ['cliente' => $c->id]) }}" wire:navigate class="btn-rojo btn"><x-icono nombre="mas" clase="size-4" /> Registrar visita</a>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi etiqueta="Compras 90 días" :valor="Formato::compacto($total90)" icono="grafico" />
        <x-kpi etiqueta="Compras del año" :valor="Formato::compacto($totalAnio)" icono="barras" tono="violeta" />
        <x-kpi etiqueta="Saldo por cobrar" :valor="Formato::compacto($saldo)" icono="billete" tono="rojo" :detalle="$this->notas->count().' nota(s)'" />
        <x-kpi etiqueta="Vencido" :valor="Formato::compacto($vencido)" icono="alerta" tono="ambar" :detalle="$ultimaCompra ? 'Última compra '.\Illuminate\Support\Carbon::parse($ultimaCompra)->diffForHumans() : 'Sin compras'" />
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="tarjeta overflow-hidden">
            <div class="border-b px-5 py-3.5 linea"><h3 class="font-semibold">Pedidos</h3></div>
            <div class="divide-y linea">
                @forelse ($this->pedidos as $p)
                    <a href="{{ route('pedidos.ver', $p) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 linea hover:bg-[var(--papel-2)]">
                        <div class="min-w-0 flex-1"><div class="text-sm font-medium">{{ $p->numero }}</div><div class="text-xs suave">{{ Formato::fecha($p->fecha) }} · {{ $p->vendedor_nombre }}</div></div>
                        <x-estado :valor="$p->estado_despacho" />
                        <span class="w-24 text-right text-sm font-semibold numero">{{ Formato::bs($p->total) }}</span>
                    </a>
                @empty
                    <x-vacio icono="caja" titulo="Sin pedidos registrados" class="!py-8" />
                @endforelse
            </div>
        </section>

        <section class="tarjeta overflow-hidden">
            <div class="border-b px-5 py-3.5 linea"><h3 class="font-semibold">Notas con saldo</h3></div>
            <div class="divide-y linea">
                @forelse ($this->notas as $n)
                    <a href="{{ $usuario->puedeCobrar() ? route('cobranza', ['nota' => $n->id]) : '#' }}" wire:navigate class="flex items-center gap-3 px-5 py-3 linea hover:bg-[var(--papel-2)]">
                        <div class="min-w-0 flex-1"><div class="text-sm font-medium">Nota {{ $n->numero }} @if ($n->estaVencida())<span class="pastilla tono-rojo">Vencida</span>@endif</div><div class="text-xs suave">Vence {{ Formato::fecha($n->fecha_vencimiento) }} · total {{ Formato::bs($n->total) }}</div></div>
                        <span class="text-sm font-semibold numero">{{ Formato::bs($n->saldo_pendiente) }}</span>
                    </a>
                @empty
                    <x-vacio icono="check-circulo" titulo="Al día: no tiene saldo pendiente" class="!py-8" />
                @endforelse
            </div>
        </section>

        <section class="tarjeta p-5">
            <h3 class="font-semibold">Productos habituales</h3>
            <ul class="mt-3 space-y-2.5">
                @forelse ($this->habituales as $h)
                    <li class="flex items-center justify-between gap-3 text-sm"><span class="truncate">{{ $h->producto_descripcion }}</span><span class="shrink-0 text-xs suave">{{ $h->veces }} ve(z/ces) · {{ Formato::cantidad($h->cantidad) }} {{ $h->producto_unidad }}</span></li>
                @empty
                    <li class="text-sm suave">Sin historial de productos.</li>
                @endforelse
            </ul>
        </section>

        <section class="tarjeta p-5">
            <h3 class="font-semibold">Visitas y competencia</h3>
            <ol class="mt-3 space-y-3">
                @foreach ($this->visitas as $v)
                    <li class="text-sm">
                        <div class="flex items-center gap-2"><span class="pastilla tono-azul">{{ $v->motivo->value }}</span><span class="text-xs suave">{{ Formato::relativa($v->fecha) }} · {{ $v->vendedor_nombre }}</span></div>
                        @if ($v->comentario)<div class="mt-1 suave">{{ \Illuminate\Support\Str::limit($v->comentario, 140) }}</div>@endif
                    </li>
                @endforeach
                @foreach ($this->competencia as $r)
                    <li class="text-sm"><span class="pastilla tono-naranja">Competencia</span> {{ $r->competidor }} · {{ $r->descripcion }} a <strong>{{ Formato::bs($r->precio) }}</strong> <span class="text-xs suave">({{ Formato::fecha($r->fecha) }})</span></li>
                @endforeach
            </ol>
        </section>
    </div>
</div>
