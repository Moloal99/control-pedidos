<?php

namespace Tests;

use App\Correo\Avisos;
use App\Enums\Rol;
use App\Models\Almacen;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Servicios\GuardarPedido;
use App\Servicios\RegistrarVisita;
use App\Support\Almacenes;
use App\Support\Rastro;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, User> */
    protected array $u = [];

    protected Cliente $cliente;

    /** @var array<string, Producto> */
    protected array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        Rastro::olvidar();
    }

    /** Almacenes, usuarios de cada rol, un cliente y productos de tres almacenes. */
    protected function escenario(): void
    {
        foreach (config('pedidos.almacenes') as $orden => $a) {
            Almacen::query()->create($a + ['orden' => $orden]);
        }
        Almacenes::olvidar();

        $this->u = [
            'admin' => User::factory()->rol(Rol::Admin)->create(['codigo' => 'ADM', 'name' => 'Admin Prueba']),
            'vendedor' => User::factory()->create(['codigo' => 'V07', 'name' => 'Vendedor Uno']),
            'otro' => User::factory()->create(['codigo' => 'V08', 'name' => 'Vendedor Dos']),
            'supervisor' => User::factory()->supervisor()->create(['codigo' => 'V03', 'name' => 'Supervisor']),
            'despacha' => User::factory()->despacha()->create(['codigo' => 'V12', 'name' => 'Vendedor Despacha']),
            'almacen' => User::factory()->rol(Rol::Almacen)->create(['codigo' => 'ALM', 'name' => 'Almacen']),
            'cobranzas' => User::factory()->rol(Rol::Cobranzas)->create(['codigo' => 'CAJ', 'name' => 'Caja']),
            'cobrador' => User::factory()->rol(Rol::Cobrador)->create(['codigo' => 'C01', 'name' => 'Cobrador']),
        ];

        $this->cliente = Cliente::query()->create(['codigo' => 'C001', 'nombre' => 'CLIENTE DE PRUEBA', 'direccion' => '0', 'telefono' => '70000000']);

        $this->p = [
            'bolsa' => Producto::query()->create(['codigo' => '5T20101', 'descripcion' => 'BOLSA 40X60', 'unidad' => 'KG', 'peso_promedio' => 1, 'micronaje' => '45']),
            'vaso' => Producto::query()->create(['codigo' => '5T20303', 'descripcion' => 'VASO 12 OZ', 'unidad' => 'PZA', 'peso_promedio' => 0.01]),
            'saco' => Producto::query()->create(['codigo' => '5T20601', 'descripcion' => 'SACO RAFIA', 'unidad' => 'PZA', 'peso_promedio' => 0.07]),
            'raro' => Producto::query()->create(['codigo' => 'X999', 'descripcion' => 'SIN PREFIJO', 'unidad' => 'PZA', 'peso_promedio' => 0]),
        ];
    }

    /** Los correos se registran pero no se envian en las pruebas. */
    protected function sinCorreos(): object
    {
        $falso = new class extends Avisos
        {
            public array $enviados = [];

            public function nuevoPedido($p, $u): void
            {
                $this->enviados[] = 'nuevo_pedido';
            }

            public function cobranzaPendiente($c, $u): void
            {
                $this->enviados[] = 'cobranza_pendiente';
            }

            public function notaAnulada($n, $u): void
            {
                $this->enviados[] = 'nota_anulada';
            }

            public function cobroConfirmado($c, $u): void
            {
                $this->enviados[] = 'cobro_confirmado';
            }

            public function correccionNumero($c, $a, $u): void
            {
                $this->enviados[] = 'correccion';
            }

            public function proformasModificadas($p, $c, $u): void
            {
                $this->enviados[] = 'proforma_modificada';
            }
        };

        $this->app->instance(Avisos::class, $falso);

        return $falso;
    }

    /**
     * Visita + pedido con bolsa (P11), vaso (P12) y saco (P15).
     *
     * @param  list<array{producto_id:int, cantidad:float, precio:float}>|null  $carrito
     */
    protected function pedido(?User $quien = null, ?User $vendedor = null, ?array $carrito = null, string $forma = 'Crédito'): Pedido
    {
        $quien ??= $this->u['vendedor'];
        $vendedor ??= $quien;
        Auth::setUser($quien);

        $visita = app(RegistrarVisita::class)->ejecutar($quien, $vendedor, [
            'cliente_id' => $this->cliente->id,
            'tipo_contacto' => 'Visita',
            'motivo' => 'Pedido',
            'forma_pago' => $forma,
            'dias_credito' => $forma === 'Crédito' ? 30 : null,
            'comentario' => 'Entregar en depósito.',
        ]);

        return app(GuardarPedido::class)->ejecutar($quien, $visita->fresh('vendedor'), $carrito ?? [
            ['producto_id' => $this->p['bolsa']->id, 'cantidad' => 100, 'precio' => 20],
            ['producto_id' => $this->p['vaso']->id, 'cantidad' => 500, 'precio' => 0.5],
            ['producto_id' => $this->p['saco']->id, 'cantidad' => 200, 'precio' => 1.5],
        ]);
    }
}
