<?php

namespace App\Livewire\Admin;

use App\Enums\AccionBitacora;
use App\Livewire\Pantalla;
use App\Models\Ajuste;
use App\Models\Almacen;
use App\Support\Almacenes;
use App\Support\Rastro;
use Livewire\Attributes\Title;

/**
 * Lo que en la Power App estaba fijo en el OnStart («unico lugar donde se
 * editan las direcciones»): ahora se edita en pantalla y queda en la bitacora.
 */
#[Title('Configuración')]
class Configuracion extends Pantalla
{
    /** @var array<string, string> */
    public array $ajustes = [];

    /** @var array<int, array<string, string>> */
    public array $almacenes = [];

    private const CLAVES = ['correo_rol_almacen', 'correo_rol_cobranzas', 'correos_anulacion_fijos', 'correo_copia_externa', 'anulacion_avisa_todos_los_almacenes', 'ventana_dias'];

    public function mount(): void
    {
        foreach (self::CLAVES as $clave) {
            $this->ajustes[$clave] = (string) Ajuste::valor($clave, '');
        }

        $this->almacenes = Almacen::query()->orderBy('orden')->get()
            ->mapWithKeys(fn (Almacen $a) => [$a->id => $a->only(['codigo', 'nombre', 'area', 'prefijos', 'correos_encargados', 'color'])])->all();
    }

    public function guardarAjustes(): void
    {
        $this->validate([
            'ajustes.correo_rol_almacen' => ['nullable', 'string', 'max:500'],
            'ajustes.correo_rol_cobranzas' => ['nullable', 'string', 'max:500'],
            'ajustes.correos_anulacion_fijos' => ['nullable', 'string', 'max:2000'],
            'ajustes.correo_copia_externa' => ['nullable', 'string', 'max:500'],
            'ajustes.ventana_dias' => ['required', 'integer', 'min:7', 'max:3650'],
        ], [], ['ajustes.ventana_dias' => 'ventana de días']);

        $antes = [];

        foreach (self::CLAVES as $clave) {
            $antes[$clave] = (string) Ajuste::valor($clave, '');
            Ajuste::fijar($clave, trim((string) ($this->ajustes[$clave] ?? '')));
        }

        Rastro::registrar(AccionBitacora::Editar, 'Cambió los ajustes de correos y listas', 'Ajuste', null, ['antes' => $antes, 'despues' => $this->ajustes]);
        $this->avisar('exito', 'Ajustes guardados');
    }

    public function guardarAlmacen(int $id): void
    {
        $this->validate([
            "almacenes.{$id}.nombre" => ['required', 'string', 'max:80'],
            "almacenes.{$id}.prefijos" => ['nullable', 'string', 'max:120'],
            "almacenes.{$id}.correos_encargados" => ['nullable', 'string', 'max:500'],
            "almacenes.{$id}.color" => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [], ["almacenes.{$id}.nombre" => 'nombre']);

        $almacen = Almacen::query()->findOrFail($id);
        $antes = $almacen->only(['nombre', 'prefijos', 'correos_encargados', 'color']);
        $almacen->update(collect($this->almacenes[$id])->only(['nombre', 'area', 'prefijos', 'correos_encargados', 'color'])->all());
        Almacenes::olvidar();

        Rastro::registrar(AccionBitacora::Editar, "Cambió la configuración del {$almacen->nombre}", 'Almacen', $almacen->id, ['antes' => $antes, 'despues' => $almacen->only(array_keys($antes))]);
        $this->avisar('exito', "{$almacen->nombre} guardado");
    }

    public function render()
    {
        return view('pantallas.admin.configuracion');
    }
}
