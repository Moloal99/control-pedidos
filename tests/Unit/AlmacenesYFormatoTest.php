<?php

namespace Tests\Unit;

use App\Enums\EstadoPago;
use App\Models\Almacen;
use App\Support\Almacenes;
use App\Support\Formato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlmacenesYFormatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefijos_de_la_power_app(): void
    {
        foreach (config('pedidos.almacenes') as $orden => $a) {
            Almacen::query()->create($a + ['orden' => $orden]);
        }
        Almacenes::olvidar();

        $this->assertSame('P11', Almacenes::paraProducto('5T20199')->codigo);
        $this->assertSame('P11', Almacenes::paraProducto('5t20201')->codigo);
        $this->assertSame('P12', Almacenes::paraProducto('5T203XX')->codigo);
        $this->assertSame('P13', Almacenes::paraProducto('5T204')->codigo);
        $this->assertSame('P14', Almacenes::paraProducto('5T20500')->codigo);
        $this->assertSame('P15', Almacenes::paraProducto('5T20601')->codigo);
        $this->assertSame('SIN', Almacenes::paraProducto('ABC')->codigo);
        $this->assertSame('ALMACÉN P11 — EXTRUSIÓN', Almacenes::paraProducto('5T201')->nombre);
    }

    public function test_formatos(): void
    {
        $this->assertSame('Bs 1,234.50', Formato::bs(1234.5));
        $this->assertSame('12.5', Formato::cantidad('12.500'));
        $this->assertSame('1,250', Formato::cantidad(1250));
        $this->assertSame(EstadoPago::Pagado, EstadoPago::segun(100, 100));
        $this->assertSame(EstadoPago::Parcial, EstadoPago::segun(100, 40));
        $this->assertSame(EstadoPago::Pendiente, EstadoPago::segun(100, 0));
    }
}
