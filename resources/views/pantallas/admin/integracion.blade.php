@php use App\Support\Formato; @endphp
<div class="space-y-5">
    <section class="grid gap-3 md:grid-cols-4">
        <div class="tarjeta p-4">
            <div class="rotulo">Modo</div>
            <div class="mt-1 flex items-center gap-2 text-lg font-bold">
                <span @class(['size-2.5 rounded-full', 'bg-emerald-500' => $conectado, 'bg-amber-500' => ! $conectado])></span>
                {{ $conectado ? 'SharePoint' : 'Demostración' }}
            </div>
            <div class="text-xs suave">{{ $conectado ? $sitio : 'Datos locales; nada sale del servidor' }}</div>
        </div>
        <div class="tarjeta p-4">
            <div class="rotulo">App de Entra ID</div>
            <div class="mt-1 text-lg font-bold">{{ $credenciales ? 'Configurada' : 'Pendiente' }}</div>
            <div class="text-xs suave">{{ $certificado ? 'Con certificado (adjuntos habilitados)' : ($credenciales ? 'Con secreto: los adjuntos requieren certificado' : 'Vea docs/03-GUIA-ENTRA-ID.md') }}</div>
        </div>
        <div class="tarjeta p-4">
            <div class="rotulo">Envío de correo</div>
            <div class="mt-1 text-lg font-bold">{{ ['graph' => 'Microsoft Graph', 'log' => 'Solo registro', 'smtp' => 'SMTP'][$mailer] ?? $mailer }}</div>
            <div class="text-xs suave">{{ $mailer === 'log' ? 'Los correos se escriben en el log, no se envían' : 'Remitente '.config('microsoft.correo.remitente') }}</div>
        </div>
        <div class="tarjeta p-4">
            <div class="rotulo">Correos fallidos</div>
            <div @class(['mt-1 text-lg font-bold', 'text-rojo-600' => $fallidos])>{{ $fallidos }}</div>
            <div class="text-xs suave">Se pueden reenviar desde la bandeja</div>
        </div>
    </section>

    <section class="tarjeta overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3.5 linea">
            <div>
                <h3 class="font-semibold">Listas de SharePoint</h3>
                <p class="text-xs suave">Se sincronizan los cambios cada minuto y una pasada completa a las 03:30.</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="sincronizar" wire:loading.attr="disabled" class="btn-borde btn btn-chico"><x-icono nombre="refrescar" clase="size-4" /> Sincronizar cambios</button>
                <button wire:click="sincronizar(true)" wire:loading.attr="disabled" wire:confirm="La pasada completa relee todas las listas y puede tardar varios minutos. ¿Continuar?" class="btn-fantasma btn btn-chico">Completa</button>
            </div>
        </div>
        <table class="tabla">
            <thead><tr><th>Lista</th><th>Última sincronización</th><th class="text-right">Filas</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($listas as $l)
                    <tr>
                        <td class="font-medium numero">{{ $l->lista }}</td>
                        <td class="text-sm">{{ $l->ultima_at ? Formato::relativa($l->ultima_at) : '—' }}</td>
                        <td class="text-right numero">{{ $l->filas }}</td>
                        <td>@if ($l->estado === 'ok')<span class="pastilla tono-verde">Al día</span>@elseif ($l->estado === 'error')<span class="pastilla tono-rojo" title="{{ $l->error }}">Error</span>@else<span class="pastilla tono-neutro">Pendiente</span>@endif
                            @if ($l->error)<div class="mt-1 max-w-md truncate text-xs text-rojo-600" title="{{ $l->error }}">{{ $l->error }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-vacio icono="nube" titulo="Todavía no se sincronizó ninguna lista" texto="Cuando el sistema esté en modo SharePoint, aquí verá el estado de las 11 listas." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="tarjeta overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3.5 linea">
            <h3 class="font-semibold">Bandeja de correos</h3>
            <div class="flex gap-2">
                <select wire:model.live="estadoCorreo" class="campo !w-40 !py-1.5"><option value="">Todos</option><option value="en_cola">En cola</option><option value="enviado">Enviados</option><option value="fallido">Fallidos</option></select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Asunto</th><th>Destinatarios</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($correos as $c)
                        <tr>
                            <td class="text-xs whitespace-nowrap suave">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                            <td class="max-w-md text-sm">{{ $c->asunto }}</td>
                            <td class="max-w-xs truncate text-xs suave" title="{{ $c->destinatarios }}">{{ $c->destinatarios }}</td>
                            <td>
                                @if ($c->estado === 'enviado')<span class="pastilla tono-verde">Enviado</span>@elseif ($c->estado === 'fallido')<span class="pastilla tono-rojo" title="{{ $c->error }}">Fallido</span>@else<span class="pastilla tono-neutro">En cola</span>@endif
                            </td>
                            <td>@if ($c->estado === 'fallido')<button wire:click="reintentar({{ $c->id }})" class="btn-fantasma btn btn-chico">Reenviar</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-vacio icono="correo" titulo="Sin correos registrados" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $correos->links() }}</div>
        <form wire:submit="probarCorreo" class="flex flex-col gap-2 border-t p-4 sm:flex-row linea fondo-papel-2">
            <input type="email" wire:model="correoPrueba" class="campo flex-1" placeholder="correo@plasticoscarmen.com">
            <button class="btn-borde btn"><x-icono nombre="correo" clase="size-4" /> Enviar correo de prueba</button>
        </form>
    </section>
</div>
