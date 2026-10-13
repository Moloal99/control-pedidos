@php use App\Support\Formato; @endphp
<div class="space-y-4">
    <div class="tarjeta flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
        <div class="flex-1 text-sm suave">
            <x-icono nombre="info" clase="mr-1 inline size-4" />
            Los usuarios, roles e indicadores salen de la lista <strong class="tinta">dim_VENDEDORES</strong> de SharePoint. Para cambiar un rol, edítelo allí; aquí solo puede suspender el acceso al sistema.
        </div>
        <input type="search" wire:model.live.debounce.300ms="buscar" class="campo sm:!w-72" placeholder="Buscar por nombre, correo o código">
    </div>

    <div class="tarjeta overflow-x-auto">
        <table class="tabla">
            <thead><tr><th>Usuario</th><th>Rol</th><th>Indicadores</th><th class="text-right">Pedidos</th><th class="text-right">Sesiones</th><th>Último acceso</th><th>Acceso</th><th></th></tr></thead>
            <tbody>
                @foreach ($usuarios as $u)
                    <tr @class(['opacity-60' => ! $u->activo])>
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-full bg-marca-50 text-xs font-bold text-marca-700 dark:bg-marca-500/20 dark:text-marca-200">{{ $u->iniciales() }}</span>
                                <div><div class="font-medium">{{ $u->name }}</div><div class="text-xs suave">{{ $u->email }}{{ $u->codigo ? ' · '.$u->codigo : '' }}</div></div>
                            </div>
                        </td>
                        <td><span class="pastilla tono-azul">{{ $u->rol->etiqueta() }}</span></td>
                        <td class="space-x-1">
                            @if ($u->carga_por_otros)<span class="pastilla tono-neutro">Supervisor</span>@endif
                            @if ($u->despacha)<span class="pastilla tono-neutro">Despacha</span>@endif
                            @if ($u->sp_id)<span class="pastilla tono-verde" title="Sincronizado con SharePoint">SP</span>@endif
                        </td>
                        <td class="text-right numero">{{ $u->pedidos_vendidos_count }}</td>
                        <td class="text-right numero">{{ $u->sesiones_count }}</td>
                        <td class="text-sm suave">{{ $u->ultimo_acceso_at ? Formato::relativa($u->ultimo_acceso_at) : 'Nunca' }}</td>
                        <td>
                            <button wire:click="alternarActivo({{ $u->id }})" @class(['relative inline-flex h-6 w-11 items-center rounded-full transition', 'bg-emerald-500' => $u->activo, 'bg-slate-300' => ! $u->activo]) title="{{ $u->activo ? 'Suspender' : 'Habilitar' }}">
                                <span @class(['inline-block size-5 rounded-full bg-white shadow transition', 'translate-x-5' => $u->activo, 'translate-x-0.5' => ! $u->activo])></span>
                            </button>
                        </td>
                        <td><a href="{{ route('admin.trazabilidad', ['usuario' => $u->id, 'vista' => 'bitacora', 'desde' => now()->subDays(29)->toDateString()]) }}" wire:navigate class="btn-fantasma btn btn-chico"><x-icono nombre="huella" clase="size-4" /> Trazabilidad</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
