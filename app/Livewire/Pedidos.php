<?php

namespace App\Livewire;

use App\Enums\EstadoDespacho;
use App\Models\Ajuste;
use App\Models\Pedido;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Lista de pedidos («Tickets» en la Power App). Ya no hay limite fijo de 90
 * dias: es el rango por omision y se puede ampliar.
 */
#[Title('Pedidos')]
class Pedidos extends Pantalla
{
    use WithPagination;

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'vendedor', except: '')]
    public string $vendedor = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        abort_unless($this->usuario()->veTabPedidos() || $this->usuario()->veTodosLosPedidos(), 403);

        // El almacen entra directo a su bandeja de trabajo
        if ($this->estado === '' && $this->usuario()->esAlmacen() && ! request()->has('estado')) {
            $this->estado = 'pendientes';
        }
    }

    public function updating($propiedad): void
    {
        if (in_array($propiedad, ['estado', 'buscar', 'vendedor', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    private function base()
    {
        $dias = (int) Ajuste::valor('ventana_dias', 90);

        return Pedido::query()
            ->visiblesPara($this->usuario())
            ->where('fecha', '>=', $this->desde !== '' ? $this->desde : now()->subDays($dias)->startOfDay())
            ->when($this->hasta !== '', fn ($q) => $q->where('fecha', '<=', $this->hasta.' 23:59:59'))
            ->when($this->vendedor !== '', fn ($q) => $q->where('vendedor_codigo', $this->vendedor))
            ->buscar($this->buscar);
    }

    /** @return array<string, int> */
    #[Computed]
    public function conteos(): array
    {
        $porEstado = $this->base()->selectRaw('estado_despacho, count(*) as n')->groupBy('estado_despacho')->pluck('n', 'estado_despacho');

        return [
            '' => (int) $porEstado->sum(),
            'sin_proforma' => (int) ($porEstado[EstadoDespacho::SinProforma->value] ?? 0),
            'pendientes' => (int) (($porEstado[EstadoDespacho::Proformado->value] ?? 0) + ($porEstado[EstadoDespacho::Parcial->value] ?? 0)),
            'despachados' => (int) ($porEstado[EstadoDespacho::Total->value] ?? 0),
        ];
    }

    #[Computed]
    public function vendedores()
    {
        return User::query()->whereNotNull('codigo')->whereIn('rol', ['user', 'admin'])->orderBy('name')->get(['codigo', 'name']);
    }

    public function render()
    {
        $pedidos = $this->base()
            ->when($this->estado === 'sin_proforma', fn ($q) => $q->where('estado_despacho', EstadoDespacho::SinProforma->value))
            ->when($this->estado === 'pendientes', fn ($q) => $q->whereIn('estado_despacho', [EstadoDespacho::Proformado->value, EstadoDespacho::Parcial->value]))
            ->when($this->estado === 'despachados', fn ($q) => $q->where('estado_despacho', EstadoDespacho::Total->value))
            ->withCount('proformas')
            ->latest('fecha')
            ->paginate(20);

        return view('pantallas.pedidos', ['pedidos' => $pedidos, 'usuario' => $this->usuario()]);
    }
}
