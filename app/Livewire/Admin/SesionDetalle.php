<?php

namespace App\Livewire\Admin;

use App\Livewire\Pantalla;
use App\Models\Sesion;

/** Recorrido completo de una sesion: pantalla por pantalla y accion por accion. */
class SesionDetalle extends Pantalla
{
    public Sesion $sesion;

    public function mount(Sesion $sesion): void
    {
        $this->sesion = $sesion->load(['usuario', 'paginas', 'asientos' => fn ($q) => $q->oldest('created_at')]);
    }

    public function render()
    {
        // Pantallas y acciones intercaladas en orden cronologico
        $linea = $this->sesion->paginas->toBase()->map(fn ($p) => ['tipo' => 'pagina', 'momento' => $p->entrada_at, 'item' => $p])
            ->merge($this->sesion->asientos->toBase()->map(fn ($a) => ['tipo' => 'accion', 'momento' => $a->created_at, 'item' => $a]))
            ->sortBy('momento')->values();

        return view('pantallas.admin.sesion', ['linea' => $linea])
            ->title('Sesión de '.($this->sesion->usuario?->name ?? '—'));
    }
}
