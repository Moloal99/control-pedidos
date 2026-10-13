<?php

namespace Tests\Feature;

use App\Enums\EstadoCobranza;
use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\EstadoProforma;
use App\Models\Bitacora;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Servicios\AnularNota;
use App\Servicios\ConfirmarCobranza;
use App\Servicios\CorregirNumeroCobranza;
use App\Servicios\CrearNotaVenta;
use App\Servicios\NumerarProforma;
use App\Servicios\RegistrarCobranza;
use App\Servicios\RegistrarVisita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * El circuito completo de la Power App: visita → pedido → proformas por
 * almacen → numeracion → despacho → anulacion → cobranza → confirmacion.
 */
class FlujoComercialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->escenario();
    }

    private function comprobante(): UploadedFile
    {
        return UploadedFile::fake()->create('comprobante.pdf', 20, 'application/pdf');
    }

    private function despachar(Pedido $pedido, string $almacen, string $numero, ?array $cantidades = null): NotaVenta
    {
        $proforma = $pedido->proformas()->where('almacen_codigo', $almacen)->firstOrFail();

        if (! $proforma->tieneNumero()) {
            app(NumerarProforma::class)->ejecutar($this->u['almacen'], $proforma, 'PF-'.$almacen.'-'.$pedido->id);
        }

        $cantidades ??= $proforma->lineas()->pluck('cantidad', 'id')->map(fn ($c) => (float) $c)->all();

        return app(CrearNotaVenta::class)->ejecutar($this->u['almacen'], $proforma->fresh(), $numero, $cantidades, $this->comprobante());
    }

    public function test_el_pedido_arma_una_proforma_por_almacen_y_queda_sin_proforma(): void
    {
        $avisos = $this->sinCorreos();
        $pedido = $this->pedido();

        $this->assertMatchesRegularExpression('/^PED-V07-\d{8}-\d{6}$/', $pedido->numero);
        $this->assertSame(EstadoDespacho::SinProforma, $pedido->estado_despacho);
        $this->assertSame(EstadoPago::Pendiente, $pedido->estado_pago);
        $this->assertEquals(2000 + 250 + 300, (float) $pedido->total);
        $this->assertEqualsCanonicalizing(['P11', 'P12', 'P15'], $pedido->proformas()->pluck('almacen_codigo')->all());
        $this->assertSame($pedido->numero.'-P12', $pedido->proformas()->where('almacen_codigo', 'P12')->value('clave'));
        $this->assertSame(['nuevo_pedido'], $avisos->enviados);
        $this->assertEquals(30, $pedido->dias_credito);
        $this->assertTrue($pedido->fecha_vencimiento->isSameDay($pedido->fecha->copy()->addDays(30)));
    }

    public function test_un_producto_sin_prefijo_conocido_va_a_la_proforma_sin_almacen(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido(carrito: [['producto_id' => $this->p['raro']->id, 'cantidad' => 3, 'precio' => 2]]);

        $this->assertSame(['SIN'], $pedido->proformas()->pluck('almacen_codigo')->all());
    }

    public function test_validaciones_del_pedido(): void
    {
        $this->sinCorreos();

        $casos = [
            'piezas con decimales' => [['producto_id' => $this->p['vaso']->id, 'cantidad' => 1.5, 'precio' => 1], 'Las piezas se venden enteras'],
            'cantidad cero' => [['producto_id' => $this->p['bolsa']->id, 'cantidad' => 0, 'precio' => 1], 'La cantidad tiene que ser 1 o más'],
            'precio cero' => [['producto_id' => $this->p['bolsa']->id, 'cantidad' => 2, 'precio' => 0], 'El precio unitario tiene que ser mayor a cero'],
        ];

        foreach ($casos as $nombre => [$linea, $mensaje]) {
            try {
                $this->pedido(carrito: [$linea]);
                $this->fail("No falló: {$nombre}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($mensaje, collect($e->errors())->flatten()->first(), $nombre);
            }
        }

        $this->expectException(ValidationException::class);
        $this->pedido(carrito: [
            ['producto_id' => $this->p['bolsa']->id, 'cantidad' => 1, 'precio' => 1],
            ['producto_id' => $this->p['bolsa']->id, 'cantidad' => 2, 'precio' => 1],
        ]);
    }

    public function test_la_visita_exige_los_datos_de_la_power_app(): void
    {
        $this->expectExceptionMessage('Falta indicar los días de crédito');

        app(RegistrarVisita::class)->ejecutar($this->u['vendedor'], $this->u['vendedor'], [
            'cliente_id' => $this->cliente->id, 'tipo_contacto' => 'Visita', 'motivo' => 'Pedido',
            'forma_pago' => 'Crédito', 'dias_credito' => 0, 'comentario' => 'x',
        ]);
    }

    public function test_el_supervisor_carga_a_nombre_de_otro_y_queda_registrado(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido($this->u['supervisor'], $this->u['vendedor']);

        $this->assertSame('V07', $pedido->vendedor_codigo);
        $this->assertSame('Supervisor', $pedido->cargado_por);
        $this->assertSame($this->u['supervisor']->id, $pedido->cargado_por_id);
        $this->assertStringStartsWith('PED-V07-', $pedido->numero);
        $this->assertTrue($this->u['supervisor']->puedeEditar($pedido));
        $this->assertFalse($this->u['supervisor']->puedeDespachar($pedido));
    }

    public function test_despacho_parcial_total_y_estados(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido();
        $p11 = $pedido->proformas()->where('almacen_codigo', 'P11')->first();
        $linea = $p11->lineas()->first();

        app(NumerarProforma::class)->ejecutar($this->u['almacen'], $p11, '40001');
        $this->assertSame(EstadoDespacho::Proformado, $pedido->fresh()->estado_despacho);

        // Mas de lo pendiente: error, sin recorte silencioso
        try {
            $this->despachar($pedido, 'P11', '27001', [$linea->id => 150]);
            $this->fail('Debió rechazar la cantidad');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('supera lo pendiente', collect($e->errors())->flatten()->first());
        }

        $nota = $this->despachar($pedido, 'P11', '27001', [$linea->id => 40]);
        $this->assertEquals(800, (float) $nota->total);
        $this->assertSame(EstadoDespacho::Parcial, $pedido->fresh()->estado_despacho);
        $this->assertSame(EstadoProforma::Parcial, $p11->fresh()->estado);
        $this->assertEquals(40, (float) $linea->fresh()->cantidad_despachada);
        $this->assertNotNull($nota->comprobante_ruta);

        // Numero de nota unico en toda la empresa
        $this->expectExceptionMessage('Ya existe una nota de venta con ese número');
        $this->despachar($pedido, 'P11', '27001', [$linea->id => 10]);
    }

    public function test_despachar_todo_deja_el_pedido_despachado_total(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido();

        $this->despachar($pedido, 'P11', 'N1');
        $this->despachar($pedido, 'P12', 'N2');
        $this->despachar($pedido, 'P15', 'N3');

        $pedido->refresh();
        $this->assertSame(EstadoDespacho::Total, $pedido->estado_despacho);
        $this->assertTrue($pedido->proformas->every(fn ($p) => $p->estado === EstadoProforma::Despachada));
        $this->assertEquals((float) $pedido->total, (float) $pedido->proformas->sum('total_despachado'));
    }

    public function test_anular_devuelve_lo_pendiente_y_avisa(): void
    {
        $avisos = $this->sinCorreos();
        $pedido = $this->pedido();
        $nota = $this->despachar($pedido, 'P12', 'N10');

        try {
            app(AnularNota::class)->ejecutar($this->u['almacen'], $nota, 'corto');
            $this->fail('El motivo corto debió fallar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('al menos 10 caracteres', collect($e->errors())->flatten()->first());
        }

        app(AnularNota::class)->ejecutar($this->u['almacen'], $nota, 'El cliente rechazó la mercadería.');

        $this->assertTrue($nota->fresh()->anulada);
        $this->assertSame('Almacen', $nota->fresh()->anulada_por);
        $this->assertSame(EstadoDespacho::Proformado, $pedido->fresh()->estado_despacho);
        $this->assertContains('nota_anulada', $avisos->enviados);

        // Las cantidades vuelven a estar disponibles: se puede despachar de nuevo
        $otra = $this->despachar($pedido, 'P12', 'N11');
        $this->assertFalse($otra->anulada);
    }

    public function test_no_se_anula_una_nota_con_cobros(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido();
        $nota = $this->despachar($pedido, 'P11', 'N20');

        app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, [
            'hubo_pago' => true, 'tipo_pago' => 'Parcial', 'monto' => 100, 'forma_pago' => 'Efectivo', 'fecha_proximo_pago' => null,
        ], null);

        $this->expectExceptionMessage('Esta nota tiene cobranzas registradas. No se puede anular.');
        app(AnularNota::class)->ejecutar($this->u['almacen'], $nota, 'Motivo suficientemente largo.');
    }

    public function test_cobranza_confirmacion_y_saldos(): void
    {
        $avisos = $this->sinCorreos();
        $pedido = $this->pedido();
        $nota = $this->despachar($pedido, 'P11', 'N30'); // Bs 2000

        // Transferencia sin comprobante: error
        try {
            app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, [
                'hubo_pago' => true, 'tipo_pago' => 'Parcial', 'monto' => 500, 'forma_pago' => 'Transferencia', 'fecha_proximo_pago' => null,
            ], null);
            $this->fail('Debió exigir comprobante');
        } catch (ValidationException $e) {
            $this->assertSame('Adjunte el comprobante de la transferencia', collect($e->errors())->flatten()->first());
        }

        $cobro = app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, [
            'hubo_pago' => true, 'tipo_pago' => 'Parcial', 'monto' => 1500, 'forma_pago' => 'Transferencia', 'fecha_proximo_pago' => null,
        ], $this->comprobante());

        // El saldo NO cambia hasta confirmar; el disponible si descuenta lo pendiente
        $this->assertEquals(2000, (float) $nota->fresh()->saldo_pendiente);
        $this->assertEquals(500, $nota->fresh()->disponibleParaCobrar());

        // No se puede cargar mas de lo disponible
        try {
            app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota->fresh(), [
                'hubo_pago' => true, 'tipo_pago' => 'Parcial', 'monto' => 800, 'forma_pago' => 'Efectivo', 'fecha_proximo_pago' => null,
            ], null);
            $this->fail('Debió rechazar el exceso');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no puede superar lo disponible', collect($e->errors())->flatten()->first());
        }

        app(ConfirmarCobranza::class)->ejecutar($this->u['cobranzas'], $cobro, 'b00012345');

        $nota->refresh();
        $this->assertSame(EstadoCobranza::Confirmada, $cobro->fresh()->estado);
        $this->assertSame('B00012345', $cobro->fresh()->numero);
        $this->assertSame('Caja', $cobro->fresh()->confirmada_por);
        $this->assertEquals(1500, (float) $nota->total_pagado);
        $this->assertEquals(500, (float) $nota->saldo_pendiente);
        $this->assertSame(EstadoPago::Parcial, $nota->estado_pago);
        // El pedido ahora refleja lo cobrado (en la Power App quedaba en 0)
        $this->assertEquals(1500, (float) $pedido->fresh()->total_pagado);
        $this->assertSame(EstadoPago::Parcial, $pedido->fresh()->estado_pago);

        // Idempotente: confirmar dos veces no suma dos veces
        try {
            app(ConfirmarCobranza::class)->ejecutar($this->u['cobranzas'], $cobro, 'B00099999');
            $this->fail('Debió avisar que ya estaba confirmada');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya fue confirmada', collect($e->errors())->flatten()->first());
        }
        $this->assertEquals(1500, (float) $nota->fresh()->total_pagado);

        // Completo = todo el disponible
        $resto = app(RegistrarCobranza::class)->ejecutar($this->u['cobrador'], $nota->fresh(), [
            'hubo_pago' => true, 'tipo_pago' => 'Completo', 'monto' => null, 'forma_pago' => 'Efectivo', 'fecha_proximo_pago' => null,
        ], null);
        $this->assertEquals(500, (float) $resto->monto);
        $this->assertSame('Cobrador', $resto->cargado_por);

        // Numero de cobranza unico
        try {
            app(ConfirmarCobranza::class)->ejecutar($this->u['cobranzas'], $resto, 'B00012345');
            $this->fail('Debió rechazar el número repetido');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Ya existe una cobranza registrada con ese número', collect($e->errors())->flatten()->first());
        }

        app(ConfirmarCobranza::class)->ejecutar($this->u['cobranzas'], $resto, 'B00012346');
        $this->assertSame(EstadoPago::Pagado, $nota->fresh()->estado_pago);
        $this->assertEquals(0, (float) $nota->fresh()->saldo_pendiente);

        // Correccion del numero con motivo
        app(CorregirNumeroCobranza::class)->ejecutar($this->u['cobranzas'], $resto->fresh(), 'B00012399', 'Se tipeó mal el último dígito.');
        $this->assertSame('B00012346', $resto->fresh()->numero_anterior);
        $this->assertContains('cobro_confirmado', $avisos->enviados);
        $this->assertContains('correccion', $avisos->enviados);
    }

    public function test_gestion_sin_pago_exige_fecha_y_no_lleva_forma_de_pago(): void
    {
        $this->sinCorreos();
        $nota = $this->despachar($this->pedido(), 'P11', 'N40');

        try {
            app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, ['hubo_pago' => false, 'tipo_pago' => null, 'monto' => null, 'forma_pago' => 'Transferencia', 'fecha_proximo_pago' => null], null);
            $this->fail('Debió exigir la fecha');
        } catch (ValidationException $e) {
            $this->assertSame('Indique la fecha del próximo pago', collect($e->errors())->flatten()->first());
        }

        $c = app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, ['hubo_pago' => false, 'tipo_pago' => null, 'monto' => null, 'forma_pago' => 'Transferencia', 'fecha_proximo_pago' => now()->addWeek()->toDateString()], null);

        $this->assertNull($c->forma_pago);
        $this->assertEquals(0, (float) $c->monto);
    }

    public function test_cada_paso_queda_en_la_bitacora_del_pedido(): void
    {
        $this->sinCorreos();
        $pedido = $this->pedido();
        $this->despachar($pedido, 'P11', 'N50');

        $acciones = Bitacora::query()->where('referencia', $pedido->numero)->pluck('accion')->map->value->all();

        $this->assertContains('crear', $acciones);
        $this->assertContains('numerar', $acciones);
        $this->assertContains('despachar', $acciones);
        $this->assertSame($this->u['almacen']->id, Bitacora::query()->where('referencia', $pedido->numero)->where('accion', 'despachar')->value('user_id'));
    }
}
