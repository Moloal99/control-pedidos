@php
    use App\Models\Sesion;
    use App\Support\Formato;
    $m = $this->metricas;
    $maxDia = max(1, $m['dias']->max('sesiones'));
    $maxHora = max(1, $m['horas']->max('total'));
@endphp
<div class="space-y-5">
    {{-- ===== Filtros ===== --}}
    <section class="tarjeta p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex gap-1 rounded-xl p-1 fondo-papel-2">
                @foreach ([1 => 'Hoy', 7 => '7 días', 30 => '30 días', 90 => '90 días'] as $d => $t)
                    <button type="button" wire:click="rango({{ $d }})" class="rounded-lg px-3 py-1.5 text-sm font-semibold suave hover:bg-[var(--papel)]">{{ $t }}</button>
                @endforeach
            </div>
            <div><label class="etiqueta">Desde</label><input type="date" wire:model.live="desde" class="campo"></div>
            <div><label class="etiqueta">Hasta</label><input type="date" wire:model.live="hasta" class="campo"></div>
            <div class="min-w-48 flex-1">
                <label class="etiqueta">Usuario</label>
                <select wire:model.live="usuario" class="campo">
                    <option value="">Todos</option>
                    @foreach ($this->usuarios as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <a href="{{ route('admin.trazabilidad.exportar', ['desde' => $desde, 'hasta' => $hasta, 'usuario' => $usuario, 'accion' => $accion, 'texto' => $texto]) }}" class="btn-borde btn"><x-icono nombre="descarga" clase="size-4" /> Excel</a>
        </div>
    </section>

    {{-- ===== Indicadores ===== --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-kpi etiqueta="Usuarios activos" :valor="$m['usuarios']" icono="usuarios" :detalle="$m['abiertas'].' sesión(es) abiertas'" />
        <x-kpi etiqueta="Sesiones" :valor="$m['sesiones']" icono="huella" tono="violeta" :detalle="'Promedio '.Sesion::formatear($m['promedio_sesion'])" />
        <x-kpi etiqueta="Tiempo total" :valor="Sesion::formatear($m['segundos_total'])" icono="reloj" tono="ambar" />
        <x-kpi etiqueta="Pantallas vistas" :valor="$m['paginas']" icono="ojo" tono="verde" />
        <x-kpi etiqueta="Acciones" :valor="$m['acciones']" icono="rayo" tono="rojo" class="col-span-2 lg:col-span-1" />
    </section>

    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <section class="tarjeta p-5">
            <h3 class="font-semibold">Sesiones por día</h3>
            <div class="mt-5 flex h-36 items-end gap-1">
                @foreach ($m['dias'] as $d)
                    <div class="group relative flex h-full flex-1 flex-col justify-end" title="{{ $d['fecha']->format('d/m') }} · {{ $d['sesiones'] }} sesión(es) · {{ $d['usuarios'] }} usuario(s) · {{ $d['acciones'] }} acción(es)">
                        <div class="w-full rounded-t bg-gradient-to-t from-marca-600 to-marca-400 group-hover:opacity-80" style="height: {{ $d['sesiones'] ? max(4, round($d['sesiones'] / $maxDia * 100)) : 2 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex justify-between text-[10px] suave"><span>{{ $m['dias']->first()['fecha']->format('d/m') ?? '' }}</span><span>{{ $m['dias']->last()['fecha']->format('d/m') ?? '' }}</span></div>
        </section>
        <section class="tarjeta p-5">
            <h3 class="font-semibold">Horario de uso</h3>
            <div class="mt-5 flex h-36 items-end gap-1">
                @foreach ($m['horas'] as $h)
                    <div class="flex h-full flex-1 flex-col justify-end" title="{{ $h['hora'] }}:00 · {{ $h['total'] }} pantalla(s)">
                        <div class="w-full rounded-t bg-gradient-to-t from-rojo-600 to-rojo-500" style="height: {{ $h['total'] ? max(4, round($h['total'] / $maxHora * 100)) : 2 }}%; opacity: {{ $h['total'] ? 1 : .25 }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1.5 flex justify-between text-[10px] suave"><span>6:00</span><span>14:00</span><span>22:00</span></div>
        </section>
    </div>

    {{-- ===== Vistas ===== --}}
    <div class="flex gap-1 rounded-2xl border p-1 fondo-papel-2 linea">
        @foreach (['usuarios' => ['Por usuario', 'usuarios'], 'bitacora' => ['Bitácora de acciones', 'documento'], 'sesiones' => ['Sesiones', 'huella'], 'pantallas' => ['Pantallas y acciones', 'barras']] as $clave => [$t, $ic])
            <button type="button" wire:click="$set('vista', '{{ $clave }}')" @class(['flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold', 'bg-[var(--papel)] shadow-sm tinta' => $vista === $clave, 'suave' => $vista !== $clave])>
                <x-icono :nombre="$ic" clase="size-4" /><span class="hidden sm:inline">{{ $t }}</span>
            </button>
        @endforeach
    </div>

    @if ($vista === 'usuarios')
        <section class="tarjeta overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Usuario</th><th class="text-right">Sesiones</th><th class="text-right">Tiempo</th><th class="text-right">Pantallas</th><th class="text-right">Altas</th><th class="text-right">Despachos</th><th class="text-right">Cobros</th><th>Último acceso</th><th></th></tr></thead>
                <tbody>
                    @forelse ($this->porUsuario as $r)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="relative grid size-9 place-items-center rounded-full bg-marca-50 text-xs font-bold text-marca-700 dark:bg-marca-500/20 dark:text-marca-200">{{ $r['usuario']->iniciales() }}
                                        @if ($r['en_linea'])<span class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full bg-emerald-500 ring-2 ring-[var(--papel)]" title="En línea"></span>@endif
                                    </span>
                                    <div><div class="font-medium">{{ $r['usuario']->name }}</div><div class="text-xs suave">{{ $r['usuario']->rol?->etiqueta() }}{{ $r['usuario']->codigo ? ' · '.$r['usuario']->codigo : '' }}</div></div>
                                </div>
                            </td>
                            <td class="text-right numero">{{ $r['sesiones'] }}</td>
                            <td class="text-right font-semibold numero">{{ Sesion::formatear($r['segundos']) }}</td>
                            <td class="text-right numero">{{ $r['pantallas'] }}</td>
                            <td class="text-right numero">{{ $r['altas'] }}</td>
                            <td class="text-right numero">{{ $r['despachos'] }}</td>
                            <td class="text-right numero">{{ $r['cobros'] }}</td>
                            <td class="text-sm suave">{{ $r['ultima'] ? Formato::relativa($r['ultima']) : '—' }}</td>
                            <td><button wire:click="verUsuario({{ $r['usuario']->id }})" class="btn-fantasma btn btn-chico">Ver acciones</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-vacio icono="usuarios" titulo="Nadie usó el sistema en este rango" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @elseif ($vista === 'bitacora')
        <section class="tarjeta overflow-hidden">
            <div class="flex flex-col gap-2 border-b p-3 sm:flex-row linea">
                <input type="search" wire:model.live.debounce.300ms="texto" class="campo flex-1" placeholder="Buscar en la descripción, documento o usuario…">
                <select wire:model.live="accion" class="campo sm:!w-56"><option value="">Todas las acciones</option>@foreach ($acciones as $v => $e)<option value="{{ $v }}">{{ $e }}</option>@endforeach</select>
            </div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Descripción</th><th>Documento</th></tr></thead>
                    <tbody>
                        @forelse ($asientos as $a)
                            <tr>
                                <td class="text-xs whitespace-nowrap suave">{{ $a->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="text-sm whitespace-nowrap">{{ $a->usuario?->name ?? $a->usuario_nombre ?? '—' }}</td>
                                <td><span class="pastilla tono-{{ $a->accion->tono() }}">{{ $a->accion->etiqueta() }}</span></td>
                                <td class="max-w-xl text-sm">{{ $a->descripcion }}</td>
                                <td class="text-xs whitespace-nowrap">
                                    @if ($a->referencia && str_starts_with($a->referencia, 'PED-'))
                                        <a href="{{ route('pedidos.ver', $a->referencia) }}" wire:navigate class="text-marca-600 hover:underline">{{ $a->referencia }}</a>
                                    @else {{ $a->referencia ?? '' }} @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-vacio icono="documento" titulo="Sin acciones para estos filtros" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $asientos->links() }}</div>
        </section>
    @elseif ($vista === 'sesiones')
        <section class="tarjeta overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Usuario</th><th>Inicio</th><th>Duración</th><th class="text-right">Pantallas</th><th class="text-right">Acciones</th><th>Equipo</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($sesiones as $s)
                            <tr class="cursor-pointer" onclick="Livewire.navigate('{{ route('admin.trazabilidad.sesion', $s) }}')">
                                <td class="font-medium">{{ $s->usuario?->name }}</td>
                                <td class="text-sm">{{ $s->iniciada_at->format('d/m/Y H:i') }}</td>
                                <td class="font-semibold numero">{{ $s->duracionLegible() }}</td>
                                <td class="text-right numero">{{ $s->paginas_count }}</td>
                                <td class="text-right numero">{{ $s->asientos_count }}</td>
                                <td class="text-xs suave">{{ $s->navegador }} · {{ $s->plataforma }} · {{ $s->ip }}</td>
                                <td>@if ($s->abierta())<span class="pastilla tono-verde">Abierta</span>@elseif ($s->motivo_cierre === 'salida')<span class="pastilla tono-neutro">Cerró sesión</span>@else<span class="pastilla tono-ambar">Expirada</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $sesiones->links() }}</div>
        </section>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="tarjeta p-5">
                <h3 class="font-semibold">Dónde pasan más tiempo</h3>
                @php($maxP = max(1, $m['pantallas_top']->max('segundos')))
                <ul class="mt-4 space-y-3">
                    @foreach ($m['pantallas_top'] as $p)
                        <li>
                            <div class="flex justify-between text-sm"><span>{{ $p['etiqueta'] }}</span><span class="suave">{{ Sesion::formatear($p['segundos']) }} · {{ $p['visitas'] }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-[var(--borde)]"><div class="h-full rounded-full bg-marca-500" style="width: {{ round($p['segundos'] / $maxP * 100) }}%"></div></div>
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="tarjeta p-5">
                <h3 class="font-semibold">Qué hacen</h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($m['acciones_top'] as $a)
                        <li class="flex items-center justify-between"><span class="pastilla tono-{{ $a['tono'] }}">{{ $a['etiqueta'] }}</span><span class="font-semibold numero">{{ $a['total'] }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>
    @endif
</div>
