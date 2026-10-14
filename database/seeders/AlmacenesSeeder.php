<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Support\Almacenes;
use Illuminate\Database\Seeder;

/** Crea los almacenes P11–P15 y SIN si faltan (no pisa lo editado en pantalla). */
class AlmacenesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('pedidos.almacenes') as $orden => $almacen) {
            Almacen::query()->firstOrCreate(['codigo' => $almacen['codigo']], $almacen + ['orden' => $orden]);
        }

        Almacenes::olvidar();
    }
}
