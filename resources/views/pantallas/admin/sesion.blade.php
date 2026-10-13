@php $s = $sesion; @endphp
<div class="space-y-5">
    <a href="{{ route('admin.trazabilidad', ['vista' => 'sesiones']) }}" wire:navigate class="inline-flex items-center gap-1 text-sm suave hover:text-marca-600"><x-icono nombre="flecha-izq" clase="size-4" /> Trazabilidad</a>
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-kpi etiqueta="Usuario" :valor="$s->usuario?->nombreCorto() ?? '—'" icono="usuario" :detalle="$s->usuario?->email" />
        <x-kpi etiqueta="Duración" :valor="$s->duracionLegible()" icono="reloj" tono="ambar" :detalle="$s->iniciada_at->format('d/m/Y H:i').' → '.($s->cerrada_at?->format('H:i') ?? 'en curso')" />
        <x-kpi etiqueta="Pantallas" :valor="$s->paginas->count()" icono="ojo" tono="verde" />
        <x-kpi etiqueta="Equipo" :valor="$s->plataforma ?? '—'" icono="monitor" tono="violeta" :detalle="$s->navegador.' · '.$s->ip" />
    </section>

    <section class="tarjeta p-5">
        <h3 class="font-semibold">Recorrido</h3>
        <ol class="relative mt-5 space-y-4 border-l-2 pl-6 linea">
            @foreach ($linea as $e)
                <li class="relative">
                    @if ($e['tipo'] === 'pagina')
                        <span class="absolute top-1 -left-[31px] grid size-5 place-items-center rounded-full bg-marca-100 text-marca-700 ring-4 ring-[var(--papel)] dark:bg-marca-500/30 dark:text-marca-100"><x-icono nombre="ojo" clase="size-3" /></span>
                        <div class="text-sm"><span class="font-medium">{{ $e['item']->titulo }}</span> <span class="suave">· {{ $e['item']->permanenciaLegible() }}</span></div>
                        <div class="text-xs suave">{{ $e['momento']->format('H:i:s') }}</div>
                    @else
                        <span class="absolute top-1 -left-[31px] size-5 rounded-full ring-4 ring-[var(--papel)] tono-{{ $e['item']->accion->tono() }}" style="background: currentColor"></span>
                        <div class="flex flex-wrap items-center gap-2 text-sm"><span class="pastilla tono-{{ $e['item']->accion->tono() }}">{{ $e['item']->accion->etiqueta() }}</span>{{ $e['item']->descripcion }}</div>
                        <div class="text-xs suave">{{ $e['momento']->format('H:i:s') }}{{ $e['item']->referencia ? ' · '.$e['item']->referencia : '' }}</div>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
