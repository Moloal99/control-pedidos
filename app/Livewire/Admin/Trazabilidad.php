<?php

namespace App\Livewire\Admin;

use App\Enums\AccionBitacora;
use App\Livewire\Pantalla;
use App\Models\User;
use App\Support\Rastro;
use App\Support\Trazas;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Panel de trazabilidad (misma logica que el portal central) con la mirada
 * por usuario: quien entro, cuanto estuvo, por donde paso y que hizo.
 */
#[Title('Trazabilidad')]
class Trazabilidad extends Pantalla
{
    use WithPagination;

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $usuario = '';

    #[Url(except: '')]
    public string $accion = '';

    #[Url(as: 'q', except: '')]
    public string $texto = '';

    #[Url(except: 'usuarios')]
    public string $vista = 'usuarios';

    public function mount(): void
    {
        // Antes de contar se cierran las sesiones que quedaron abiertas
        Rastro::vencerInactivas();
        $this->desde = $this->desde ?: now()->subDays(6)->toDateString();
        $this->hasta = $this->hasta ?: now()->toDateString();
    }

    public function updating($propiedad): void
    {
        $this->resetPage('sesiones');
        $this->resetPage('bitacora');
    }

    private function filtros(): array
    {
        return Trazas::filtros(['desde' => $this->desde, 'hasta' => $this->hasta, 'usuario' => $this->usuario, 'accion' => $this->accion, 'texto' => $this->texto]);
    }

    #[Computed]
    public function metricas(): array
    {
        return Trazas::metricas($this->filtros());
    }

    #[Computed]
    public function porUsuario()
    {
        return Trazas::porUsuario($this->filtros());
    }

    #[Computed]
    public function usuarios()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function rango(int $dias): void
    {
        $this->desde = now()->subDays($dias - 1)->toDateString();
        $this->hasta = now()->toDateString();
    }

    public function verUsuario(int $id): void
    {
        $this->usuario = (string) $id;
        $this->vista = 'bitacora';
    }

    public function render()
    {
        $f = $this->filtros();

        return view('pantallas.admin.trazabilidad', [
            'filtros' => $f,
            'sesiones' => $this->vista === 'sesiones'
                ? Trazas::sesiones($f)->with('usuario')->withCount(['paginas', 'asientos'])->latest('iniciada_at')->paginate(15, pageName: 'sesiones')
                : null,
            'asientos' => $this->vista === 'bitacora'
                ? Trazas::bitacora($f)->with('usuario')->latest('created_at')->paginate(25, pageName: 'bitacora')
                : null,
            'acciones' => AccionBitacora::opciones(),
        ]);
    }
}
