<?php

namespace Database\Seeders;

use App\Correo\Avisos;
use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\User;
use App\Servicios\AnularNota;
use App\Servicios\ConfirmarCobranza;
use App\Servicios\CrearNotaVenta;
use App\Servicios\EstadoPedido;
use App\Servicios\GuardarPedido;
use App\Servicios\GuardarRelevamiento;
use App\Servicios\NumerarProforma;
use App\Servicios\RegistrarCobranza;
use App\Servicios\RegistrarVisita;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de demostracion con nombres ficticios. Los movimientos se generan
 * con los mismos servicios que usan las pantallas, asi la demo recorre la
 * logica real (estados, saldos, bitacora) a lo largo de las ultimas semanas.
 */
class DemoSeeder extends Seeder
{
    private Carbon $hoy;

    public function run(): void
    {
        mt_srand(2026);
        $this->hoy = Carbon::now();

        // Los avisos de la demo no se mandan
        app()->instance(Avisos::class, new class extends Avisos
        {
            public function nuevoPedido($p, $u): void {}

            public function cobranzaPendiente($c, $u): void {}

            public function notaAnulada($n, $u): void {}

            public function cobroConfirmado($c, $u): void {}

            public function correccionNumero($c, $a, $u): void {}

            public function proformasModificadas($p, $c, $u): void {}
        });

        $usuarios = $this->usuarios();
        $clientes = $this->clientes();
        $productos = $this->productos();

        $this->movimientos($usuarios, $clientes, $productos);

        Carbon::setTestNow();
        Auth::logout();
    }

    /** Ningun movimiento de la demo queda en el futuro. */
    private function tope(Carbon $momento): Carbon
    {
        return $momento->gt($this->hoy) ? $this->hoy->copy()->subMinutes(mt_rand(5, 90)) : $momento;
    }

    /** @return array<string, User> */
    private function usuarios(): array
    {
        $datos = [
            'admin' => ['codigo' => 'ADM', 'name' => 'Alan Moscoso', 'email' => 'amoscoso@plasticoscarmen.com', 'rol' => Rol::Admin],
            'vendedor' => ['codigo' => 'V07', 'name' => 'Jorge Peña Salvatierra', 'email' => 'demo.vendedor@plasticoscarmen.com', 'rol' => Rol::Vendedor],
            'vendedora' => ['codigo' => 'V12', 'name' => 'Lucía Fernández Roca', 'email' => 'demo.vendedora@plasticoscarmen.com', 'rol' => Rol::Vendedor, 'despacha' => true],
            'vendedor3' => ['codigo' => 'V15', 'name' => 'Marco Antonio Suárez', 'email' => 'demo.vendedor3@plasticoscarmen.com', 'rol' => Rol::Vendedor],
            'supervisor' => ['codigo' => 'V03', 'name' => 'Ricardo Áñez Vaca', 'email' => 'demo.supervisor@plasticoscarmen.com', 'rol' => Rol::Vendedor, 'carga_por_otros' => true],
            'almacen' => ['codigo' => 'ALM', 'name' => 'Patricia Rivero Justiniano', 'email' => 'demo.almacen@plasticoscarmen.com', 'rol' => Rol::Almacen],
            'cobranzas' => ['codigo' => 'CAJ', 'name' => 'Caja Central', 'email' => 'demo.caja@plasticoscarmen.com', 'rol' => Rol::Cobranzas],
            'cobrador' => ['codigo' => 'C01', 'name' => 'Hugo Méndez Arteaga', 'email' => 'demo.cobrador@plasticoscarmen.com', 'rol' => Rol::Cobrador],
        ];

        return collect($datos)->map(fn ($d) => User::query()->updateOrCreate(['email' => $d['email']], $d + ['activo' => true]))->all();
    }

    /** @return list<Cliente> */
    private function clientes(): array
    {
        $nombres = [
            'DISTRIBUIDORA EL TRÉBOL', 'COMERCIAL SANTA ROSA', 'SUPERMERCADO LA FAMILIA', 'FERRETERÍA DON BOSCO',
            'AVÍCOLA SAN JUAN', 'PANIFICADORA LA ESPIGA', 'IMPORTADORA ANDINA', 'MERCADO MUTUALISTA PUESTO 14',
            'LÁCTEOS DEL ORIENTE', 'FRIGORÍFICO LOS TAJIBOS', 'AGROPECUARIA LA CHIQUITANA', 'RESTAURANTE EL PATIO',
            'COOPERATIVA AGRÍCOLA GUARAYOS', 'BOLSAS Y EMPAQUES CAMIRI', 'MINIMARKET 24 HORAS', 'HELADOS POLAR',
            'CONFITERÍA LA COLONIAL', 'EMBUTIDOS SANTA CRUZ', 'DISTRIBUIDORA NORTE INTEGRADO', 'FARMACIA SAN MARTÍN',
            'COMERCIAL LOS POZOS', 'INDUSTRIA DE ALIMENTOS SIRIONÓ', 'TIENDA DOÑA CARMEN', 'MAYORISTA LA RAMADA',
        ];
        $zonas = ['Av. Banzer 4to anillo', 'Calle Sucre 245', 'Av. Cristo Redentor km 6', 'Mercado Los Pozos pasillo 3', 'Av. Santos Dumont 3er anillo', 'Plan 3000 UV 160', 'Calle Warnes 88', 'Av. Alemana 5to anillo'];

        return collect($nombres)->map(fn ($n, $i) => Cliente::query()->updateOrCreate(['codigo' => 'C'.str_pad((string) (1040 + $i * 7), 5, '0', STR_PAD_LEFT)], [
            'nombre' => $n,
            'direccion' => $i % 5 === 3 ? '0' : $zonas[$i % count($zonas)],
            'telefono' => $i % 6 === 2 ? '0' : '7'.mt_rand(1000000, 9999999),
        ]))->all();
    }

    /** @return list<Producto> */
    private function productos(): array
    {
        $catalogo = [
            // P11 Extrusion (5T201 / 5T202)
            ['5T20101', 'BOLSA PE ALTA DENSIDAD 40X60 NEGRA', 'KG', '45', 1.0, 100, 10],
            ['5T20102', 'BOLSA PE BAJA DENSIDAD 30X40 TRANSPARENTE', 'KG', '25', 1.0, 200, 10],
            ['5T20103', 'BOLSA CAMISETA 35X50 BLANCA', 'PAQ', '18', 0.85, 100, 20],
            ['5T20104', 'BOLSA BASURA 70X90 NEGRA', 'PAQ', '60', 1.6, 10, 25],
            ['5T20201', 'BOBINA STRETCH FILM 50CM', 'PZA', '23', 2.4, null, null],
            ['5T20202', 'BOLSA ROLLO 25X35 PICADO', 'PZA', '15', 0.6, null, null],
            // P12 Termoformado
            ['5T20301', 'BANDEJA TERMOFORMADA T-12 TRANSPARENTE', 'PZA', null, 0.012, 50, 20],
            ['5T20302', 'ENVASE BISAGRA 500 ML', 'PZA', null, 0.018, 25, 20],
            ['5T20303', 'VASO PP 12 OZ CRISTAL', 'PZA', null, 0.009, 50, 20],
            ['5T20304', 'TAPA DOMO PARA VASO 12 OZ', 'PZA', null, 0.004, 100, 10],
            // P13 Expandido
            ['5T20401', 'VASO TÉRMICO EXPANDIDO 8 OZ', 'PZA', null, 0.003, 25, 40],
            ['5T20402', 'BANDEJA EXPANDIDO 2P BLANCA', 'PZA', null, 0.005, 100, 5],
            ['5T20403', 'CONTENEDOR ALMUERZO EXPANDIDO', 'PZA', null, 0.009, 100, 5],
            // P14 Inyeccion
            ['5T20501', 'TAPA ROSCA 38 MM', 'PZA', null, 0.0025, 500, 4],
            ['5T20502', 'BALDE 20 L CON TAPA', 'PZA', null, 0.95, null, null],
            ['5T20503', 'CAJA COSECHERA 25 KG', 'PZA', null, 1.8, null, null],
            // P15 Rafia
            ['5T20601', 'SACO RAFIA 50X80 BLANCO', 'PZA', null, 0.066, 100, 10],
            ['5T20602', 'SACO RAFIA 60X100 LAMINADO', 'PZA', null, 0.092, 100, 10],
            ['5T20603', 'BOLSA RAFIA 65X104 IMPRESA', 'PZA', null, 0.079, 100, 10],
            ['5T20604', 'CINTA RAFIA AGRÍCOLA ROLLO 2 KG', 'PZA', null, 2.0, null, null],
        ];

        return collect($catalogo)->map(fn ($p) => Producto::query()->updateOrCreate(['codigo' => $p[0]], [
            'descripcion' => $p[1],
            'unidad' => $p[2],
            'micronaje' => $p[3],
            'peso_promedio' => $p[4],
            'bolsas_por_paquete' => $p[5],
            'paquetes_por_jaba' => $p[6],
            'jabitas_por_jaba' => $p[6] ? 4 : null,
            'jabas_por_bulto' => $p[6] ? 2 : null,
            'peso_por_bolsa' => $p[5] ? round($p[4] / max(1, $p[5]), 4) : null,
        ]))->all();
    }

    /**
     * @param  array<string, User>  $u
     * @param  list<Cliente>  $clientes
     * @param  list<Producto>  $productos
     */
    private function movimientos(array $u, array $clientes, array $productos): void
    {
        $vendedores = [$u['vendedor'], $u['vendedora'], $u['vendedor3']];
        $comprobante = fn (string $nombre) => UploadedFile::fake()->createWithContent($nombre.'.pdf', "%PDF-1.4\n% comprobante de demostracion\n");
        $precios = ['KG' => [18, 26], 'PAQ' => [35, 70], 'PZA' => [0.4, 4]];

        for ($dia = 44; $dia >= 0; $dia--) {
            $cuantos = $dia === 0 ? 2 : mt_rand(0, 2);

            for ($n = 0; $n < $cuantos; $n++) {
                $momento = $this->hoy->copy()->subDays($dia)->setTime(mt_rand(8, 17), mt_rand(0, 59));
                Carbon::setTestNow($momento);

                $vendedor = $vendedores[array_rand($vendedores)];
                $cargaSupervisor = mt_rand(1, 6) === 1;
                $quien = $cargaSupervisor ? $u['supervisor'] : $vendedor;
                Auth::setUser($quien);

                $cliente = $clientes[array_rand($clientes)];
                $credito = mt_rand(1, 3) > 1;

                $visita = app(RegistrarVisita::class)->ejecutar($quien, $vendedor, [
                    'cliente_id' => $cliente->id,
                    'tipo_contacto' => mt_rand(1, 4) === 1 ? 'Llamada' : 'Visita',
                    'motivo' => 'Pedido',
                    'forma_pago' => $credito ? 'Crédito' : 'Contado',
                    'dias_credito' => $credito ? [15, 30, 30, 45][mt_rand(0, 3)] : null,
                    'comentario' => ['Pedido de reposición mensual.', 'Entregar en depósito, horario de 8 a 12.', 'Cliente pide factura a nombre de la empresa.', 'Consultó precios de la competencia; se mantuvo el precio.'][mt_rand(0, 3)],
                ]);

                $carrito = collect($productos)->shuffle()->take(mt_rand(1, 5))->map(function (Producto $p) use ($precios) {
                    [$min, $max] = $precios[$p->unidadNormalizada()] ?? [1, 5];

                    return [
                        'producto_id' => $p->id,
                        'cantidad' => $p->unidadNormalizada() === 'KG' ? mt_rand(20, 300) : mt_rand(2, 60) * ($p->unidadNormalizada() === 'PZA' ? 50 : 1),
                        'precio' => round($min + mt_rand(0, 100) / 100 * ($max - $min), 2),
                    ];
                })->values()->all();

                $pedido = app(GuardarPedido::class)->ejecutar($quien, $visita->fresh('vendedor'), $carrito);

                // Lo de hoy queda sin procesar (bandeja del almacen)
                if ($dia < 2 && mt_rand(1, 3) > 1) {
                    continue;
                }

                $this->despachar($pedido, $u, $dia, $comprobante);
            }
        }

        // Relevamientos de competencia recientes
        foreach (range(1, 9) as $i) {
            Carbon::setTestNow($this->hoy->copy()->subDays(mt_rand(0, 12))->setTime(mt_rand(9, 17), mt_rand(0, 59)));
            $vendedor = $vendedores[array_rand($vendedores)];
            Auth::setUser($vendedor);
            app(GuardarRelevamiento::class)->ejecutar($vendedor, $vendedor, [
                'cliente_id' => $clientes[array_rand($clientes)]->id,
                'competidor' => ['Plastiforte', 'Envases del Sur', 'Polibol', 'Rafitex'][mt_rand(0, 3)],
                'tipo' => ['Bolsas', 'Bandejas', 'Vasos', 'Rafia'][mt_rand(0, 3)],
                'descripcion' => ['Bolsa negra 40x60', 'Bandeja T-12', 'Vaso 8 oz', 'Saco 50x80'][mt_rand(0, 3)],
                'medida' => ['40 X 60', null, '8 OZ', '50 X 80'][mt_rand(0, 3)],
                'unidad' => ['KG', 'PZA', 'PZA', 'PZA'][mt_rand(0, 3)],
                'precio' => mt_rand(40, 2600) / 100,
                'cantidad' => mt_rand(1, 50),
                'observacion' => mt_rand(0, 1) ? 'Ofrece entrega sin costo.' : null,
            ]);
        }
    }

    /**
     * @param  array<string, User>  $u
     */
    private function despachar(Pedido $pedido, array $u, int $dia, callable $comprobante): void
    {
        $almacen = $u['almacen'];
        $avance = now()->copy();

        foreach ($pedido->proformas()->get() as $i => $proforma) {
            Carbon::setTestNow($avance = $this->tope($avance->copy()->addMinutes(mt_rand(30, 180))));
            Auth::setUser($almacen);
            app(NumerarProforma::class)->ejecutar($almacen, $proforma, (string) (40200 + Proforma::query()->whereNotNull('numero')->count() + 1));

            if ($dia < 3 && mt_rand(1, 2) === 1) {
                continue;
            }

            // Despacho total o parcial
            $parcial = mt_rand(1, 4) === 1;
            $cantidades = $proforma->lineas()->get()->mapWithKeys(fn ($l) => [
                $l->id => $parcial ? ($l->esPorPieza() ? floor((float) $l->cantidad / 2) : round((float) $l->cantidad / 2, 1)) : (float) $l->cantidad,
            ])->all();

            Carbon::setTestNow($avance = $this->tope($avance->copy()->addHours(mt_rand(2, 20))));
            $numeroNota = (string) (27500 + NotaVenta::query()->count() + 1);
            $nota = app(CrearNotaVenta::class)->ejecutar($almacen, $proforma->fresh(), $numeroNota, $cantidades, $comprobante('nota-'.$numeroNota));

            // Una anulacion de ejemplo
            if ($pedido->id % 11 === 0) {
                Carbon::setTestNow($avance = $this->tope($avance->copy()->addHours(3)));
                app(AnularNota::class)->ejecutar($almacen, $nota, 'Cliente rechazó la mercadería por error en la medida solicitada.');

                continue;
            }

            if ($dia < 4 || mt_rand(1, 5) === 1) {
                continue;
            }

            // Cobranza del vendedor y confirmacion de caja
            Carbon::setTestNow($avance = $this->tope($avance->copy()->addDays(mt_rand(1, 6))));
            $vendedor = User::query()->where('codigo', $pedido->vendedor_codigo)->first();
            Auth::setUser($vendedor);
            $completo = mt_rand(1, 3) > 1;
            $forma = ['Efectivo', 'Transferencia', 'Depósito bancario'][mt_rand(0, 2)];

            $cobranza = app(RegistrarCobranza::class)->ejecutar($vendedor, $nota->fresh(), [
                'hubo_pago' => true,
                'tipo_pago' => $completo ? 'Completo' : 'Parcial',
                'monto' => $completo ? null : round((float) $nota->total * 0.4, 2),
                'forma_pago' => $forma,
                'fecha_proximo_pago' => $completo ? null : now()->addDays(15)->toDateString(),
            ], $forma === 'Efectivo' ? null : $comprobante('cobro-'.$nota->numero));

            if ($dia > 8 && mt_rand(1, 4) > 1) {
                Carbon::setTestNow($this->tope($avance->copy()->addHours(mt_rand(2, 30))));
                Auth::setUser($u['cobranzas']);
                app(ConfirmarCobranza::class)->ejecutar($u['cobranzas'], $cobranza, ($forma === 'Efectivo' ? 'B' : 'V').str_pad((string) (12000 + $cobranza->id), 8, '0', STR_PAD_LEFT));
            }
        }

        app(EstadoPedido::class)->recalcular($pedido);
    }
}
