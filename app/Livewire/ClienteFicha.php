<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Relevamiento;
use App\Models\Visita as VisitaModelo;
use Livewire\Attributes\Computed;

/** Ficha 360° del cliente: compras, saldo, visitas, productos habituales y competencia. */
class ClienteFicha extends Pantalla
{
    public Cliente $cliente;

    public function mount(Cliente $cliente): void
    {
        $this->cliente = $cliente;
    }

    #[Computed]
    public function pedidos()
    {
        return Pedido::query()->where('cliente_id', $this->cliente->id)->visiblesPara($this->usuario())->latest('fecha')->limit(15)->get();
    }

    #[Computed]
    public function notas()
    {
        $u = $this->usuario();

        return NotaVenta::query()->where('cliente_id', $this->cliente->id)->activas()->where('saldo_pendiente', '>', 0)
            ->when(! $u->veTodasLasNotas() && ! $u->veTodosLosPedidos(), fn ($q) => $q->where('vendedor_codigo', $u->codigo))
            ->oldest('fecha_vencimiento')->get();
    }

    #[Computed]
    public function visitas()
    {
        return VisitaModelo::query()->where('cliente_id', $this->cliente->id)->latest('fecha')->limit(10)->get();
    }

    #[Computed]
    public function habituales()
    {
        return PedidoLinea::query()
            ->whereHas('pedido', fn ($q) => $q->where('cliente_id', $this->cliente->id))
            ->selectRaw('producto_codigo, producto_descripcion, producto_unidad, count(*) as veces, sum(cantidad) as cantidad, max(id) as ultima')
            ->groupBy('producto_codigo', 'producto_descripcion', 'producto_unidad')
            ->orderByDesc('veces')->limit(8)->get();
    }

    #[Computed]
    public function competencia()
    {
        return Relevamiento::query()->where('cliente_id', $this->cliente->id)->latest('fecha')->limit(8)->get();
    }

    public function render()
    {
        $compras = Pedido::query()->where('cliente_id', $this->cliente->id);

        return view('pantallas.cliente-ficha', [
            'usuario' => $this->usuario(),
            'total90' => (float) (clone $compras)->where('fecha', '>=', now()->subDays(90))->sum('total'),
            'totalAnio' => (float) (clone $compras)->where('fecha', '>=', now()->startOfYear())->sum('total'),
            'ultimaCompra' => (clone $compras)->max('fecha'),
        ])->title($this->cliente->nombre);
    }
}
