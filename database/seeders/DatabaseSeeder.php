<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Support\Almacenes;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Configuracion minima (almacenes). En modo demo se agregan usuarios,
     * catalogo y movimientos de prueba; en modo sharepoint todo eso llega
     * con `php artisan sharepoint:sincronizar --completa`.
     */
    public function run(): void
    {
        foreach (config('pedidos.almacenes') as $orden => $almacen) {
            Almacen::query()->updateOrCreate(['codigo' => $almacen['codigo']], $almacen + ['orden' => $orden]);
        }

        Almacenes::olvidar();

        if (config('pedidos.modo') === 'demo') {
            $this->call(DemoSeeder::class);
        }
    }
}
