<?php

namespace Tests\Feature;

use App\Enums\EstadoDespacho;
use App\Models\Pedido;
use App\Servicios\CrearNotaVenta;
use App\Servicios\EditarPedido;
use App\Servicios\NumerarProforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EditarPedidoTest extends TestCase
{
    use RefreshDatabase;

    private object $avisos;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->escenario();
        $this->avisos = $this->sinCorreos();
    }

    /** @return list<array<string, mixed>> */
    private function lineas(Pedido $pedido): array
    {
        return $pedido->lineas()->get()->map(fn ($l) => ['id' => $l->id, 'producto_id' => $l->producto_id, 'cantidad' => (float) $l->cantidad, 'precio' => (float) $l->precio_unitario])->all();
    }

    public function test_cambia_cantidades_quita_y_agrega_productos(): void
    {
        $pedido = $this->pedido();
        $lineas = $this->lineas($pedido);

        $lineas[0]['cantidad'] = 150;            // bolsa: 100 → 150
        unset($lineas[2]);                        // se quita el saco (P15)
        $lineas[] = ['id' => null, 'producto_id' => $this->p['raro']->id, 'cantidad' => 4, 'precio' => 10];

        $resultado = app(EditarPedido::class)->ejecutar($this->u['vendedor'], $pedido, ['forma_pago' => 'Contado', 'dias_credito' => null], array_values($lineas));
        $pedido = $resultado['pedido'];

        $this->assertEquals(3000 + 250 + 40, (float) $pedido->total);
        $this->assertNull($pedido->dias_credito);
        $this->assertSame('Vendedor Uno', $pedido->modificado_por);
        // La proforma P15 sin numero quedo vacia y se elimino; nacio la SIN
        $this->assertEqualsCanonicalizing(['P11', 'P12', 'SIN'], $pedido->proformas()->pluck('almacen_codigo')->all());
        $this->assertSame(['nuevo_pedido', 'proforma_modificada'], $this->avisos->enviados);
        $this->assertArrayHasKey('forma_pago', $resultado['cambios']['cabecera']);
    }

    public function test_no_se_toca_un_almacen_con_notas_y_se_recalcula_el_estado(): void
    {
        $pedido = $this->pedido();
        $p12 = $pedido->proformas()->where('almacen_codigo', 'P12')->first();
        app(NumerarProforma::class)->ejecutar($this->u['almacen'], $p12, '500');
        app(CrearNotaVenta::class)->ejecutar($this->u['almacen'], $p12->fresh(), 'N1', $p12->lineas()->pluck('cantidad', 'id')->all(), UploadedFile::fake()->create('n.pdf', 5));

        $lineas = $this->lineas($pedido);
        $vaso = collect($lineas)->search(fn ($l) => $l['producto_id'] === $this->p['vaso']->id);
        $lineas[$vaso]['cantidad'] = 600;

        try {
            app(EditarPedido::class)->ejecutar($this->u['vendedor'], $pedido, ['forma_pago' => 'Crédito', 'dias_credito' => 30], $lineas);
            $this->fail('Debió bloquear la línea con nota');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('notas de venta', collect($e->errors())->flatten()->first());
        }

        // Nada cambio (todo o nada)
        $this->assertEquals(500, (float) $pedido->lineas()->where('producto_id', $this->p['vaso']->id)->value('cantidad'));
        $this->assertNull($pedido->fresh()->modificado_por);
    }

    public function test_otro_vendedor_no_puede_editar(): void
    {
        $pedido = $this->pedido();

        $this->expectExceptionMessage('Usted no puede editar este pedido.');
        app(EditarPedido::class)->ejecutar($this->u['otro'], $pedido, ['forma_pago' => 'Contado', 'dias_credito' => null], $this->lineas($pedido));
    }

    public function test_sin_cambios_no_guarda(): void
    {
        $pedido = $this->pedido();

        $this->expectExceptionMessage('No hay cambios para guardar.');
        app(EditarPedido::class)->ejecutar($this->u['vendedor'], $pedido, ['forma_pago' => 'Crédito', 'dias_credito' => 30], $this->lineas($pedido));
    }

    public function test_agregar_un_almacen_nuevo_a_un_pedido_despachado_lo_vuelve_parcial(): void
    {
        $pedido = $this->pedido(carrito: [['producto_id' => $this->p['bolsa']->id, 'cantidad' => 10, 'precio' => 5]]);
        $p11 = $pedido->proformas()->first();
        app(NumerarProforma::class)->ejecutar($this->u['almacen'], $p11, '600');
        app(CrearNotaVenta::class)->ejecutar($this->u['almacen'], $p11->fresh(), 'N2', $p11->lineas()->pluck('cantidad', 'id')->all(), UploadedFile::fake()->create('n.pdf', 5));
        $this->assertSame(EstadoDespacho::Total, $pedido->fresh()->estado_despacho);

        $lineas = $this->lineas($pedido);
        $lineas[] = ['id' => null, 'producto_id' => $this->p['saco']->id, 'cantidad' => 10, 'precio' => 2];
        app(EditarPedido::class)->ejecutar($this->u['vendedor'], $pedido, ['forma_pago' => 'Crédito', 'dias_credito' => 30], $lineas);

        // La Power App lo dejaba en «Despachado total»
        $this->assertSame(EstadoDespacho::Parcial, $pedido->fresh()->estado_despacho);
    }
}
