<?php

namespace App\Livewire;

use App\Enums\EstadoCobranza;
use App\Enums\EstadoDespacho;
use App\Models\Bitacora;
use App\Models\Cobranza;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\Visita as VisitaModelo;
use App\Support\VentaEnCurso;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Tablero de inicio: lo que cada rol tiene que hacer hoy, sus numeros y lo
 * ultimo que paso. Reemplaza la pantalla inicial fija de la Power App.
 */
#[Title('Inicio')]
class Inicio extends Pantalla
{
    #[Computed]
    public function indicadores(): array
    {
        $u = $this->usuario();
        $mes = now()->startOfMonth();
        $pedidos = Pedido::query()->visiblesPara($u);
        $notas = NotaVenta::query()->activas()->when(! $u->veTodasLasNotas() && ! $u->esCobranzas() && ! $u->esAlmacen(), fn ($q) => $q->where('vendedor_codigo', $u->codigo));

        return [
            'ventas_mes' => (float) (clone $pedidos)->where('fecha', '>=', $mes)->sum('total'),
            'pedidos_mes' => (clone $pedidos)->where('fecha', '>=', $mes)->count(),
            'visitas_hoy' => VisitaModelo::query()->when(! $u->veTodosLosPedidos(), fn ($q) => $q->where('vendedor_id', $u->id))->where('fecha', '>=', today())->count(),
            'por_cobrar' => (float) (clone $notas)->where('saldo_pendiente', '>', 0)->sum('saldo_pendiente'),
            'vencido' => (float) (clone $notas)->where('saldo_pendiente', '>', 0)->where('fecha_vencimiento', '<', now())->sum('saldo_pendiente'),
            'sin_proforma' => (clone $pedidos)->where('estado_despacho', EstadoDespacho::SinProforma->value)->count(),
            'por_despachar' => (clone $pedidos)->whereIn('estado_despacho', [EstadoDespacho::Proformado->value, EstadoDespacho::Parcial->value])->count(),
            'despachado_hoy' => (float) NotaVenta::query()->activas()->where('fecha', '>=', today())->sum('total'),
            'por_confirmar' => Cobranza::query()->where('hubo_pago', true)->where('estado', EstadoCobranza::Pendiente->value)->count(),
            'por_confirmar_bs' => (float) Cobranza::query()->where('hubo_pago', true)->where('estado', EstadoCobranza::Pendiente->value)->sum('monto'),
            'confirmado_mes' => (float) Cobranza::query()->where('estado', EstadoCobranza::Confirmada->value)->where('fecha_confirmacion', '>=', $mes)
                ->when(! $u->veTodasLasCobranzas(), fn ($q) => $q->where('vendedor_codigo', $u->codigo))->sum('monto'),
        ];
    }

    /** Ventas de los ultimos 14 dias (barras). */
    #[Computed]
    public function tendencia(): array
    {
        $desde = now()->subDays(13)->startOfDay();
        $porDia = Pedido::query()->visiblesPara($this->usuario())->where('fecha', '>=', $desde)->get(['fecha', 'total'])
            ->groupBy(fn ($p) => $p->fecha->toDateString())->map(fn ($g) => (float) $g->sum('total'));

        return collect(range(0, 13))->map(function ($i) use ($desde, $porDia) {
            $dia = $desde->copy()->addDays($i);

            return ['dia' => $dia, 'total' => $porDia[$dia->toDateString()] ?? 0.0];
        })->all();
    }

    /** Bandeja: lo que espera una accion de este usuario. */
    #[Computed]
    public function bandeja()
    {
        $u = $this->usuario();

        if ($u->esAlmacen() || $u->esAdmin() || $u->vendedorDespacha()) {
            return Pedido::query()->visiblesPara($u)
                ->when($u->vendedorDespacha() && ! $u->esAdmin() && ! $u->esAlmacen(), fn ($q) => $q->where('vendedor_codigo', $u->codigo))
                ->whereIn('estado_despacho', [EstadoDespacho::SinProforma->value, EstadoDespacho::Proformado->value, EstadoDespacho::Parcial->value])
                ->orderByRaw("CASE estado_despacho WHEN 'Sin proforma' THEN 0 WHEN 'Proformado' THEN 1 ELSE 2 END")
                ->oldest('fecha')->limit(6)->get();
        }

        return collect();
    }

    #[Computed]
    public function cobranzasPendientes()
    {
        return $this->usuario()->puedeConfirmarCobranzas()
            ? Cobranza::query()->where('hubo_pago', true)->where('estado', EstadoCobranza::Pendiente->value)->oldest('fecha')->limit(6)->get()
            : collect();
    }

    #[Computed]
    public function notasVencidas()
    {
        $u = $this->usuario();

        if (! $u->puedeCobrar()) {
            return collect();
        }

        return NotaVenta::query()->cobrables($u)->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', now())
            ->oldest('fecha_vencimiento')->limit(5)->get();
    }

    #[Computed]
    public function actividad()
    {
        $u = $this->usuario();

        return Bitacora::query()
            ->whereNotIn('accion', ['entrar', 'salir', 'ver', 'correo'])
            ->when(! $u->esAdmin(), fn ($q) => $q->where('user_id', $u->id))
            ->with('usuario')->latest('created_at')->limit(8)->get();
    }

    public function render()
    {
        return view('pantallas.inicio', [
            'usuario' => $this->usuario(),
            'visita' => VentaEnCurso::visita(),
            'carrito' => count(VentaEnCurso::carrito()),
            'hoy' => Carbon::now(),
        ]);
    }
}
