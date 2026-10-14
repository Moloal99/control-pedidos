<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\Producto;
use App\Support\Texto;
use Livewire\Attributes\Computed;

/**
 * Busqueda global (Ctrl+K): pedidos, clientes, notas y productos, mas
 * accesos rapidos a las pantallas permitidas para el rol.
 */
class Buscador extends Pantalla
{
    public string $texto = '';

    #[Computed]
    public function resultados(): array
    {
        $t = trim($this->texto);
        $u = $this->usuario();

        if (mb_strlen($t) < 2) {
            return [];
        }

        $grupos = [];

        if ($u->veTabPedidos() || $u->veTodosLosPedidos()) {
            $grupos['Pedidos'] = Pedido::query()->visiblesPara($u)->buscar($t)->latest('fecha')->limit(5)->get()
                ->map(fn ($p) => ['icono' => 'caja', 'titulo' => $p->cliente_nombre, 'detalle' => $p->numero.' · '.$p->estado_despacho?->corto(), 'url' => route('pedidos.ver', $p)])->all();
        }

        $grupos['Clientes'] = Cliente::query()->buscar($t)->orderBy('nombre')->limit(5)->get()
            ->map(fn ($c) => ['icono' => 'tienda', 'titulo' => $c->nombre, 'detalle' => 'Código '.$c->codigo, 'url' => route('clientes.ver', $c)])->all();

        if ($u->puedeCobrar() || $u->veTodosLosPedidos()) {
            $grupos['Notas de venta'] = NotaVenta::query()->where('numero', Texto::like(), "%{$t}%")
                ->when(! $u->veTodasLasNotas() && ! $u->veTodosLosPedidos(), fn ($q) => $q->where('vendedor_codigo', $u->codigo))
                ->limit(5)->get()
                ->map(fn ($n) => ['icono' => 'documento', 'titulo' => 'Nota '.$n->numero.' · '.$n->cliente_nombre, 'detalle' => ($n->anulada ? 'Anulada' : 'Saldo Bs '.number_format((float) $n->saldo_pendiente, 2)), 'url' => $n->pedido ? route('pedidos.ver', $n->pedido) : route('inicio')])->all();
        }

        $grupos['Productos'] = Producto::query()->buscar($t)->limit(4)->get()
            ->map(fn ($p) => ['icono' => 'etiqueta', 'titulo' => $p->descripcion, 'detalle' => $p->codigo.' · '.$p->almacen()->area, 'url' => $u->puedeVender() ? route('pedido') : null])->all();

        return array_filter($grupos);
    }

    /** @return list<array{icono:string, titulo:string, url:string}> */
    public function accesos(): array
    {
        $u = $this->usuario();

        return array_values(array_filter([
            $u->puedeVender() ? ['icono' => 'ubicacion', 'titulo' => 'Nueva visita', 'url' => route('visita')] : null,
            $u->puedeVender() ? ['icono' => 'carrito', 'titulo' => 'Cargar pedido', 'url' => route('pedido')] : null,
            $u->puedeCobrar() ? ['icono' => 'billete', 'titulo' => 'Registrar cobranza', 'url' => route('cobranza')] : null,
            $u->veTabCobranzas() ? ['icono' => 'recibo', 'titulo' => 'Confirmar cobranzas', 'url' => route('cobranzas.confirmar')] : null,
            ($u->veTabPedidos() || $u->veTodosLosPedidos()) ? ['icono' => 'caja', 'titulo' => 'Pedidos', 'url' => route('pedidos.index')] : null,
            $u->esAdmin() ? ['icono' => 'huella', 'titulo' => 'Trazabilidad', 'url' => route('admin.trazabilidad')] : null,
        ]));
    }

    public function render()
    {
        return view('pantallas.buscador', ['accesos' => $this->accesos()]);
    }
}
