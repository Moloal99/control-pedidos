<?php

namespace App\Livewire\Admin;

use App\Enums\AccionBitacora;
use App\Livewire\Pantalla;
use App\Models\User;
use App\Support\Rastro;
use Livewire\Attributes\Title;

/**
 * Usuarios = dim_VENDEDORES. El rol y los indicadores se editan en
 * SharePoint (fuente de verdad); aqui solo se puede suspender el acceso.
 */
#[Title('Usuarios')]
class Usuarios extends Pantalla
{
    public string $buscar = '';

    public function alternarActivo(int $id): void
    {
        $usuario = User::query()->findOrFail($id);

        if ($usuario->is($this->usuario())) {
            $this->avisar('error', 'No puede suspender su propia cuenta.');

            return;
        }

        $usuario->update(['activo' => ! $usuario->activo]);

        Rastro::registrar(AccionBitacora::Acceso, ($usuario->activo ? 'Habilitó' : 'Suspendió').' el acceso de '.$usuario->name, 'User', $usuario->id, ['activo' => $usuario->activo]);
        $this->avisar('exito', ($usuario->activo ? 'Acceso habilitado: ' : 'Acceso suspendido: ').$usuario->name);
    }

    public function render()
    {
        $usuarios = User::query()
            ->when(trim($this->buscar) !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$this->buscar.'%')->orWhere('email', 'like', '%'.$this->buscar.'%')->orWhere('codigo', 'like', $this->buscar.'%')))
            ->withCount(['pedidosVendidos', 'sesiones'])
            ->orderBy('rol')->orderBy('name')->get();

        return view('pantallas.admin.usuarios', ['usuarios' => $usuarios]);
    }
}
