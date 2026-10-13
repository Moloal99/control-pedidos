<div class="space-y-5">
    <form wire:submit="guardarAjustes" class="tarjeta space-y-5 p-5">
        <div>
            <h2 class="font-semibold">Correos de aviso</h2>
            <p class="text-sm suave">Varias direcciones se separan con punto y coma. Los cambios quedan registrados en la bitácora.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="etiqueta">Rol almacén · pedido nuevo, proforma modificada y nota anulada</label>
                <input type="text" wire:model="ajustes.correo_rol_almacen" class="campo">
            </div>
            <div>
                <label class="etiqueta">Rol cobranzas · cobranza para confirmar</label>
                <input type="text" wire:model="ajustes.correo_rol_cobranzas" class="campo">
            </div>
            <div class="md:col-span-2">
                <label class="etiqueta">Siempre reciben el aviso de nota de venta anulada</label>
                <textarea wire:model="ajustes.correos_anulacion_fijos" rows="2" class="campo"></textarea>
            </div>
            <div>
                <label class="etiqueta">Copia oculta en todos los avisos (externa)</label>
                <input type="text" wire:model="ajustes.correo_copia_externa" class="campo" placeholder="Vacío = sin copia">
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">La Power App copiaba todos los correos a un consultor externo. Complete solo si la empresa lo autoriza.</p>
            </div>
            <div>
                <label class="etiqueta">Días que muestran por omisión Pedidos y Cobranzas</label>
                <input type="number" wire:model="ajustes.ventana_dias" class="campo numero">
                @error('ajustes.ventana_dias') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-start gap-3 md:col-span-2">
                <input type="checkbox" @checked($ajustes['anulacion_avisa_todos_los_almacenes'] === '1') class="mt-1 size-4 accent-marca-600"
                       x-on:change="$wire.set('ajustes.anulacion_avisa_todos_los_almacenes', $event.target.checked ? '1' : '0')">
                <span class="text-sm"><strong>Una anulación avisa a los encargados de todos los almacenes</strong> (como la Power App). Desmárquelo para avisar solo al encargado del almacén de la nota.</span>
            </label>
        </div>
        <div class="flex justify-end"><button class="btn-primario btn"><x-icono nombre="check" clase="size-4" /> Guardar ajustes</button></div>
    </form>

    <section class="space-y-3">
        <div>
            <h2 class="font-semibold">Almacenes y proformas</h2>
            <p class="text-sm suave">Cada producto va a la proforma del almacén cuyo prefijo coincide con el inicio de su código (por ejemplo 5T201 → P11).</p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($almacenes as $id => $a)
                <form wire:submit="guardarAlmacen({{ $id }})" class="tarjeta space-y-3 p-5" wire:key="alm-{{ $id }}">
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model.live="almacenes.{{ $id }}.color" class="size-9 cursor-pointer rounded-lg border-0 bg-transparent">
                        <div class="flex-1"><div class="font-semibold" style="color: {{ $a['color'] }}">{{ $a['codigo'] }}</div><div class="text-xs suave">{{ $a['area'] }}</div></div>
                    </div>
                    <div><label class="etiqueta">Nombre (texto que se guarda en SharePoint)</label><input type="text" wire:model="almacenes.{{ $id }}.nombre" class="campo"></div>
                    <div><label class="etiqueta">Prefijos de código de producto</label><input type="text" wire:model="almacenes.{{ $id }}.prefijos" class="campo numero" placeholder="5T201,5T202"></div>
                    <div><label class="etiqueta">Encargados (aviso de anulación)</label><input type="text" wire:model="almacenes.{{ $id }}.correos_encargados" class="campo"></div>
                    <div class="flex justify-end"><button class="btn-borde btn btn-chico"><x-icono nombre="check" clase="size-4" /> Guardar</button></div>
                </form>
            @endforeach
        </div>
    </section>
</div>
