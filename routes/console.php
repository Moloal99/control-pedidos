<?php

use App\Enums\AccionBitacora;
use App\SharePoint\Sincronizador;
use App\Support\Rastro;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| sharepoint:sincronizar           trae lo que cambio desde la ultima pasada
| sharepoint:sincronizar --completa  relee todo y borra lo que ya no existe
*/
Artisan::command('sharepoint:sincronizar {--completa} {--lista=*}', function (Sincronizador $sincronizador) {
    if (config('pedidos.modo') !== 'sharepoint') {
        $this->warn('El sistema está en modo demostración (PEDIDOS_MODO=demo): no hay nada que sincronizar.');

        return 0;
    }

    $modelos = collect(Sincronizador::MODELOS)
        ->when($this->option('lista'), fn ($c) => $c->filter(fn ($m) => in_array($m::listaSharePoint(), $this->option('lista'), true)))
        ->values()->all();

    $resumen = $sincronizador->ejecutar($modelos, (bool) $this->option('completa'));

    $this->table(['Lista', 'Filas', 'Omitidas', 'Borradas', 'Error'], collect($resumen)->map(fn ($r, $lista) => [
        $lista, $r['filas'], $r['omitidas'], $r['borradas'], $r['error'] ? mb_substr($r['error'], 0, 80) : '—',
    ])->values()->all());

    if ($this->option('completa')) {
        Rastro::registrar(AccionBitacora::Sincronizar, 'Sincronización completa con SharePoint', null, null, $resumen);
    }

    return collect($resumen)->contains(fn ($r) => $r['error']) ? 1 : 0;
})->purpose('Sincroniza las listas de SharePoint con la base local');

Artisan::command('trazabilidad:vencer', function () {
    $this->info(Rastro::vencerInactivas().' sesión(es) cerradas por inactividad.');
})->purpose('Cierra las sesiones de trazabilidad sin actividad');

// Cambios cada minuto; pasada completa de madrugada (detecta borrados)
Schedule::command('sharepoint:sincronizar')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('sharepoint:sincronizar --completa')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('trazabilidad:vencer')->everyTenMinutes();
