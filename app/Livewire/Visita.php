<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\User;
use App\Servicios\RegistrarVisita;
use App\Support\VentaEnCurso;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Pantalla Visita: registrar el contacto con el cliente. Es el primer paso
 * de una venta; una visita admite varios pedidos.
 */
#[Title('Visita')]
class Visita extends Pantalla
{
    public string $buscarCliente = '';

    public ?int $cliente_id = null;

    public ?string $tipo_contacto = null;

    public ?string $motivo = null;

    public ?string $forma_pago = null;

    public ?int $dias_credito = null;

    public string $comentario = '';

    public ?int $vendedor_id = null;

    /** «Nueva visita» con productos sin guardar pide confirmacion. */
    public bool $confirmarDescarte = false;

    public function mount(): void
    {
        $this->vendedor_id = VentaEnCurso::vendedor($this->usuario())->getKey();

        if ($cliente = request()->integer('cliente')) {
            $this->elegirCliente($cliente);
        }
    }

    #[Computed]
    public function visitaActual(): ?\App\Models\Visita
    {
        return VentaEnCurso::visita();
    }

    #[Computed]
    public function clientes()
    {
        if (mb_strlen(trim($this->buscarCliente)) < 2) {
            return collect();
        }

        return Cliente::query()->buscar($this->buscarCliente)->orderBy('nombre')->limit(8)->get();
    }

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->cliente_id ? Cliente::query()->find($this->cliente_id) : null;
    }

    /** Lo que conviene saber del cliente antes de hablar con el. */
    #[Computed]
    public function resumenCliente(): ?array
    {
        if (! $this->cliente) {
            return null;
        }

        $notas = NotaVenta::query()->where('cliente_id', $this->cliente_id)->activas()->where('saldo_pendiente', '>', 0)->get();
        $ultimo = Pedido::query()->where('cliente_id', $this->cliente_id)->latest('fecha')->with('lineas')->first();

        return [
            'saldo' => (float) $notas->sum('saldo_pendiente'),
            'notas' => $notas->count(),
            'vencidas' => $notas->filter->estaVencida()->count(),
            'ultimo' => $ultimo,
            'pedidos90' => (float) Pedido::query()->where('cliente_id', $this->cliente_id)->where('fecha', '>=', now()->subDays(90))->sum('total'),
        ];
    }

    #[Computed]
    public function vendedores()
    {
        return User::query()->cargables()->get();
    }

    public function elegirCliente(int $id): void
    {
        $this->cliente_id = $id;
        $this->buscarCliente = '';
        unset($this->cliente, $this->resumenCliente);
    }

    public function quitarCliente(): void
    {
        $this->cliente_id = null;
    }

    public function updatedMotivo(?string $valor): void
    {
        if ($valor !== 'Pedido') {
            $this->forma_pago = null;
            $this->dias_credito = null;
        }
    }

    public function updatedFormaPago(?string $valor): void
    {
        if ($valor !== 'Crédito') {
            $this->dias_credito = null;
        }
    }

    public function updatedVendedorId(?int $id): void
    {
        VentaEnCurso::fijarVendedor($id ?: null);
        $this->vendedor_id = VentaEnCurso::vendedor($this->usuario())->getKey();
    }

    public function registrar(RegistrarVisita $servicio)
    {
        $usuario = $this->usuario();
        $vendedor = VentaEnCurso::vendedor($usuario);

        $visita = $this->intentar(fn () => $servicio->ejecutar($usuario, $vendedor, [
            'cliente_id' => $this->cliente_id,
            'tipo_contacto' => $this->tipo_contacto,
            'motivo' => $this->motivo,
            'forma_pago' => $this->forma_pago,
            'dias_credito' => $this->dias_credito,
            'comentario' => $this->comentario,
        ]), $vendedor->getKey() !== $usuario->getKey()
            ? "Visita registrada a nombre de {$vendedor->name}"
            : 'Visita registrada');

        if (! $visita) {
            return null;
        }

        VentaEnCurso::fijarVisita($visita);
        unset($this->visitaActual);

        // Si la visita es para pedido, se pasa directo a cargar productos
        if ($visita->esPedido()) {
            return $this->redirectRoute('pedido', navigate: true);
        }

        return null;
    }

    public function nuevaVisita(): void
    {
        $pendientes = count(VentaEnCurso::carrito());

        if ($pendientes > 0 && ! $this->confirmarDescarte) {
            $this->confirmarDescarte = true;

            return;
        }

        VentaEnCurso::reiniciar();
        $this->reset(['cliente_id', 'tipo_contacto', 'motivo', 'forma_pago', 'dias_credito', 'comentario', 'confirmarDescarte', 'buscarCliente']);
        $this->vendedor_id = $this->usuario()->getKey();
        unset($this->visitaActual);
        $this->avisar('info', 'Listo para una nueva visita');
    }

    public function render()
    {
        return view('pantallas.visita', [
            'usuario' => $this->usuario(),
            'atribuido' => VentaEnCurso::vendedor($this->usuario()),
            'carrito' => count(VentaEnCurso::carrito()),
        ]);
    }
}
