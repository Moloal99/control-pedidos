<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada rol entra a lo que le corresponde (matriz de la Power App) y todas
 * las pantallas cargan sin errores.
 */
class PantallasYPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escenario();
        $this->sinCorreos();
    }

    public function test_sin_sesion_se_va_al_acceso(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Ingresar con Microsoft 365');
    }

    public function test_acceso_de_demostracion(): void
    {
        $this->post(route('login.demo'), ['usuario' => $this->u['vendedor']->id])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($this->u['vendedor']);
    }

    public function test_un_usuario_suspendido_no_entra(): void
    {
        $this->u['otro']->update(['activo' => false]);
        $this->post(route('login.demo'), ['usuario' => $this->u['otro']->id])->assertNotFound();
        $this->assertGuest();
    }

    /**
     * @return array<string, array{0:string, 1:array<string,int>}>
     */
    public static function matriz(): array
    {
        // rol => [ruta => estado esperado]
        return [
            'vendedor' => ['vendedor', ['inicio' => 200, 'visita' => 200, 'pedido' => 200, 'cobranza' => 200, 'competencia' => 200, 'pedidos.index' => 200, 'cobranzas.confirmar' => 403, 'admin.trazabilidad' => 403]],
            'almacen' => ['almacen', ['inicio' => 200, 'visita' => 403, 'pedido' => 403, 'cobranza' => 403, 'competencia' => 403, 'pedidos.index' => 200, 'cobranzas.confirmar' => 403, 'admin.trazabilidad' => 403]],
            'cobranzas' => ['cobranzas', ['inicio' => 200, 'visita' => 403, 'cobranza' => 403, 'pedidos.index' => 200, 'cobranzas.confirmar' => 200, 'admin.trazabilidad' => 403]],
            'cobrador' => ['cobrador', ['inicio' => 200, 'visita' => 403, 'cobranza' => 200, 'pedidos.index' => 403, 'cobranzas.confirmar' => 403]],
            'admin' => ['admin', ['inicio' => 200, 'visita' => 200, 'pedido' => 200, 'cobranza' => 200, 'competencia' => 200, 'pedidos.index' => 200, 'cobranzas.confirmar' => 200, 'admin.trazabilidad' => 200, 'admin.usuarios' => 200, 'admin.configuracion' => 200, 'admin.integracion' => 200]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('matriz')]
    public function test_matriz_de_acceso_por_rol(string $rol, array $esperado): void
    {
        $this->pedido();
        $usuario = $this->u[$rol];

        foreach ($esperado as $ruta => $estado) {
            $this->actingAs($usuario)->get(route($ruta))->assertStatus($estado);
        }
    }

    public function test_el_detalle_de_pedido_respeta_la_propiedad(): void
    {
        $pedido = $this->pedido();

        $this->actingAs($this->u['vendedor'])->get(route('pedidos.ver', $pedido))->assertOk()->assertSee($pedido->numero);
        $this->actingAs($this->u['otro'])->get(route('pedidos.ver', $pedido))->assertForbidden();
        $this->actingAs($this->u['supervisor'])->get(route('pedidos.ver', $pedido))->assertOk();
        $this->actingAs($this->u['almacen'])->get(route('pedidos.ver', $pedido))->assertOk()->assertSee('Guardar');
        $this->actingAs($this->u['admin'])->get(route('clientes.ver', $this->cliente))->assertOk()->assertSee('CLIENTE DE PRUEBA');
    }

    public function test_vendedor_que_despacha_solo_despacha_lo_suyo(): void
    {
        $propio = $this->pedido($this->u['despacha']);
        $ajeno = $this->pedido();

        $this->assertTrue($this->u['despacha']->puedeDespachar($propio));
        $this->assertFalse($this->u['despacha']->puedeDespachar($ajeno));
        $this->assertTrue($this->u['almacen']->puedeDespachar($ajeno));
        $this->assertFalse($this->u['vendedor']->puedeDespachar($ajeno));
    }

    public function test_las_pantallas_quedan_en_la_trazabilidad(): void
    {
        $this->actingAs($this->u['vendedor'])->get(route('visita'))->assertOk();
        $this->actingAs($this->u['vendedor'])->get(route('pedidos.index'))->assertOk();

        $this->assertSame(['Visita', 'Pedidos'], Pagina::query()->orderBy('id')->pluck('titulo')->all());

        $this->actingAs($this->u['vendedor'])->postJson(route('trazabilidad.latido'))->assertOk()->assertJson(['ok' => true]);
        $this->assertNotNull(Pagina::query()->latest('id')->first()->salida_at);
    }

    public function test_exportacion_de_trazabilidad(): void
    {
        $this->actingAs($this->u['vendedor'])->get(route('inicio'));

        $respuesta = $this->actingAs($this->u['admin'])->get(route('admin.trazabilidad.exportar'));

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $respuesta->headers->get('Content-Type'));
    }

    public function test_comprobante_protegido(): void
    {
        $this->actingAs($this->u['otro'])->get(route('comprobantes.ver', ['nota', 999]))->assertNotFound();
        $this->assertInstanceOf(User::class, $this->u['otro']);
    }
}
