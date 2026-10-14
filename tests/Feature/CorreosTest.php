<?php

namespace Tests\Feature;

use App\Correo\Aviso;
use App\Models\Ajuste;
use App\Models\Correo;
use App\Servicios\AnularNota;
use App\Servicios\ConfirmarCobranza;
use App\Servicios\CrearNotaVenta;
use App\Servicios\EditarPedido;
use App\Servicios\NumerarProforma;
use App\Servicios\RegistrarCobranza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Los seis avisos se arman, se envian y quedan en la bandeja. */
class CorreosTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_seis_avisos_con_sus_destinatarios(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->escenario();

        $pedido = $this->pedido();
        $p11 = $pedido->proformas()->where('almacen_codigo', 'P11')->first();
        app(NumerarProforma::class)->ejecutar($this->u['almacen'], $p11, '1');
        $nota = app(CrearNotaVenta::class)->ejecutar($this->u['almacen'], $p11->fresh(), 'N1', $p11->lineas()->pluck('cantidad', 'id')->all(), UploadedFile::fake()->create('n.pdf', 4));
        $cobro = app(RegistrarCobranza::class)->ejecutar($this->u['vendedor'], $nota, ['hubo_pago' => true, 'tipo_pago' => 'Completo', 'monto' => null, 'forma_pago' => 'Efectivo', 'fecha_proximo_pago' => null], null);
        app(ConfirmarCobranza::class)->ejecutar($this->u['cobranzas'], $cobro, 'B00001');

        $p12 = $pedido->proformas()->where('almacen_codigo', 'P12')->first();
        app(NumerarProforma::class)->ejecutar($this->u['almacen'], $p12, '2');
        $otra = app(CrearNotaVenta::class)->ejecutar($this->u['almacen'], $p12->fresh(), 'N2', $p12->lineas()->pluck('cantidad', 'id')->all(), UploadedFile::fake()->create('n.pdf', 4));
        app(AnularNota::class)->ejecutar($this->u['almacen'], $otra, 'Mercadería devuelta por el cliente.');

        $lineas = $pedido->lineas()->get()->map(fn ($l) => ['id' => $l->id, 'producto_id' => $l->producto_id, 'cantidad' => (float) $l->cantidad, 'precio' => (float) $l->precio_unitario])->all();
        $saco = collect($lineas)->search(fn ($l) => $l['producto_id'] === $this->p['saco']->id);
        $lineas[$saco]['precio'] = 1.75;
        app(EditarPedido::class)->ejecutar($this->u['vendedor'], $pedido, ['forma_pago' => 'Crédito', 'dias_credito' => 30], $lineas);

        $eventos = Correo::query()->orderBy('id')->pluck('evento')->all();
        $this->assertSame(['nuevo_pedido', 'cobranza_pendiente', 'cobro_confirmado', 'nota_anulada', 'proforma_modificada'], $eventos);
        $this->assertSame(0, Correo::query()->where('estado', 'fallido')->count());

        $nuevo = Correo::query()->where('evento', 'nuevo_pedido')->first();
        $this->assertStringContainsString('agonzales@plasticoscarmen.com', $nuevo->destinatarios);
        $this->assertStringContainsString($this->u['vendedor']->email, $nuevo->destinatarios);

        $anulada = Correo::query()->where('evento', 'nota_anulada')->first();
        $this->assertStringContainsString('mrendon@plasticoscarmen.com', $anulada->destinatarios);
        $this->assertStringContainsString('acuellar@plasticoscarmen.com', $anulada->destinatarios);

        Mail::assertSent(Aviso::class, 5);
        Mail::assertSent(Aviso::class, fn (Aviso $a) => str_starts_with($a->asunto, 'Proforma modificada · ALMACÉN P15'));
    }

    public function test_el_vendedor_que_despacha_no_avisa_al_almacen_y_la_copia_externa_va_oculta(): void
    {
        Mail::fake();
        $this->escenario();
        Ajuste::fijar('correo_copia_externa', 'externo@ejemplo.com');

        $this->pedido($this->u['despacha']);

        $correo = Correo::query()->firstOrFail();
        $this->assertStringNotContainsString('agonzales@', $correo->destinatarios);
        Mail::assertSent(Aviso::class, fn (Aviso $a) => $a->hasBcc('externo@ejemplo.com') && ! $a->hasTo('externo@ejemplo.com'));
    }

    public function test_las_plantillas_se_renderizan(): void
    {
        Storage::fake('local');
        $this->escenario();
        $pedido = $this->pedido();

        $correo = Correo::query()->where('evento', 'nuevo_pedido')->firstOrFail();
        $aviso = unserialize(base64_decode($correo->carga));
        $html = $aviso->render();

        $this->assertStringContainsString($pedido->numero, $html);
        $this->assertStringContainsString('ALMACÉN P12 — TERMOFORMADO', $html);
        $this->assertStringContainsString('Bs 2,550.00', $html);
        $this->assertSame('enviado', $correo->estado);
    }
}
