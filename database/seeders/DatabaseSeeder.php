<?php

namespace Database\Seeders;

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
        $this->call(AlmacenesSeeder::class);

        if (config('pedidos.modo') === 'demo') {
            $this->call(DemoSeeder::class);
        }
    }
}
