<?php

namespace Tests\Feature;

use App\Enums\EstadoDespacho;
use App\Models\Pedido;
use App\Models\Proforma;
use App\SharePoint\Espejo;
use App\SharePoint\ErrorSharePoint;
use App\SharePoint\SharePoint;
use App\SharePoint\Sincronizador;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SharePoint en memoria: verifica que se escribe, se compensa y se sincroniza. */
class EspejoSharePointTest extends TestCase
{
    use RefreshDatabase;

    private SharePointEnMemoria $sp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escenario();
        $this->sinCorreos();

        $this->sp = new SharePointEnMemoria;
        $this->app->instance(SharePoint::class, $this->sp);
        $this->app->forgetScopedInstances();
    }

    public function test_guardar_pedido_escribe_cabecera_proformas_y_lineas(): void
    {
        $pedido = $this->pedido();

        $this->assertNotNull($pedido->sp_id);
        $this->assertCount(1, $this->sp->listas['fact_PEDIDOS']);
        $this->assertCount(3, $this->sp->listas['fact_PROFORMAS']);
        $this->assertCount(3, $this->sp->listas['fact_PEDIDO_LINEAS']);
        $this->assertCount(1, $this->sp->listas['fact_VISITAS']);

        $cabecera = $this->sp->listas['fact_PEDIDOS'][$pedido->sp_id];
        $this->assertSame($pedido->numero, $cabecera['PEDIDO_NUMERO']);
        $this->assertSame('Sin proforma', $cabecera['PEDIDO_ESTADO_DESPACHO']);
        $this->assertSame('Crédito', $cabecera['PEDIDO_FORMA_PAGO']);
        $this->assertMatchesRegularExpression('/Z$/', $cabecera['PEDIDO_FECHA']);

        $proforma = collect($this->sp->listas['fact_PROFORMAS'])->firstWhere('PROFORMA_CLAVE', $pedido->numero.'-P12');
        $this->assertSame($pedido->numero.'-P12', $proforma['PROFORMA_TITULO']);
        $this->assertSame('CLIENTE DE PRUEBA', $proforma['CLIENTE_NOMBRE']);
    }

    public function test_si_sharepoint_falla_a_mitad_no_queda_nada(): void
    {
        $this->sp->fallarEn = 'fact_PEDIDO_LINEAS';

        try {
            $this->pedido();
            $this->fail('Debió fallar');
        } catch (ErrorSharePoint) {
        }

        // Ni en la base local ni en SharePoint (la visita si quedo: es otra operacion)
        $this->assertSame(0, Pedido::query()->count());
        $this->assertSame(0, Proforma::query()->count());
        $this->assertCount(0, $this->sp->listas['fact_PEDIDOS'] ?? []);
        $this->assertCount(0, $this->sp->listas['fact_PROFORMAS'] ?? []);
        $this->assertCount(1, $this->sp->listas['fact_VISITAS']);
    }

    public function test_la_sincronizacion_trae_lo_cargado_desde_otra_herramienta(): void
    {
        $pedido = $this->pedido();

        // Alguien numera la proforma desde la Power App (escribe directo en SharePoint)
        $spProforma = $pedido->proformas()->where('almacen_codigo', 'P11')->value('sp_id');
        $this->sp->listas['fact_PROFORMAS'][$spProforma]['PROFORMA_NUMERO'] = '77777';
        $this->sp->listas['fact_PEDIDOS'][$pedido->sp_id]['PEDIDO_ESTADO_DESPACHO'] = 'Proformado';

        app(Sincronizador::class)->ejecutar([Pedido::class, Proforma::class]);

        $this->assertSame('77777', $pedido->proformas()->where('almacen_codigo', 'P11')->value('numero'));
        $this->assertSame(EstadoDespacho::Proformado, $pedido->fresh()->estado_despacho);
    }

    public function test_espejo_compensa_actualizaciones(): void
    {
        $pedido = $this->pedido();
        $espejo = app(Espejo::class);
        $this->sp->fallarEn = null;

        try {
            $espejo->operacion(function () use ($pedido, $espejo) {
                $pedido->update(['dias_credito' => 45]);
                $espejo->actualizar($pedido, ['dias_credito']);
                throw new \RuntimeException('falla posterior');
            });
        } catch (\RuntimeException) {
        }

        $this->assertEquals(30, $this->sp->listas['fact_PEDIDOS'][$pedido->sp_id]['PEDIDO_DIAS_CREDITO']);
        $this->assertEquals(30, $pedido->fresh()->dias_credito);
    }
}

/** Doble de SharePoint para las pruebas. */
class SharePointEnMemoria implements SharePoint
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $listas = [];

    public ?string $fallarEn = null;

    private int $siguiente = 100;

    public function habilitado(): bool
    {
        return true;
    }

    public function crear(string $lista, array $campos): ?int
    {
        if ($this->fallarEn === $lista) {
            throw new ErrorSharePoint("SharePoint no permitió crear un elemento en {$lista} (503)");
        }

        $id = $this->siguiente++;
        $this->listas[$lista][$id] = $campos + ['id' => $id, 'Modified' => now()->toIso8601String()];

        return $id;
    }

    public function actualizar(string $lista, int $id, array $campos): void
    {
        $this->listas[$lista][$id] = array_merge($this->listas[$lista][$id] ?? [], $campos, ['Modified' => now()->toIso8601String()]);
    }

    public function eliminar(string $lista, int $id): void
    {
        unset($this->listas[$lista][$id]);
    }

    public function obtener(string $lista, int $id): ?array
    {
        return $this->listas[$lista][$id] ?? null;
    }

    public function cambios(string $lista, ?CarbonInterface $desde = null): iterable
    {
        return array_values($this->listas[$lista] ?? []);
    }

    public function ids(string $lista): array
    {
        return array_keys($this->listas[$lista] ?? []);
    }

    public function adjuntar(string $lista, int $id, string $nombre, string $contenido): void {}

    public function descargarAdjunto(string $lista, int $id): ?array
    {
        return null;
    }
}
