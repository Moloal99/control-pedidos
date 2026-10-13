<?php

use App\Http\Controllers\Auth\MicrosoftController;
use App\Http\Controllers\Auth\SesionController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ExportarTrazabilidadController;
use App\Http\Controllers\TrazabilidadController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- acceso
Route::middleware('guest')->group(function () {
    Route::get('/acceso', [SesionController::class, 'mostrar'])->name('login');
    Route::post('/acceso/demo', [SesionController::class, 'demo'])->name('login.demo');
    Route::get('/auth/microsoft', [MicrosoftController::class, 'redirigir'])->name('auth.microsoft');
    Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'volver'])->name('auth.microsoft.volver');
});

Route::post('/salir', [SesionController::class, 'salir'])->middleware('auth')->name('salir');

// ---------------------------------------------------------------- sistema
Route::middleware('auth')->group(function () {
    Route::livewire('/', Livewire\Inicio::class)->name('inicio');

    Route::middleware('capacidad:puedeVender')->group(function () {
        Route::livewire('/visita', Livewire\Visita::class)->name('visita');
        Route::livewire('/pedido', Livewire\Carrito::class)->name('pedido');
        Route::livewire('/competencia', Livewire\Competencia::class)->name('competencia');
    });

    Route::livewire('/cobranza', Livewire\Cobranza::class)->middleware('capacidad:puedeCobrar')->name('cobranza');

    Route::livewire('/pedidos', Livewire\Pedidos::class)->name('pedidos.index');
    Route::livewire('/pedidos/{pedido}', Livewire\PedidoDetalle::class)->name('pedidos.ver');

    Route::livewire('/cobranzas', Livewire\Cobranzas::class)->middleware('capacidad:veTabCobranzas')->name('cobranzas.confirmar');

    Route::livewire('/clientes/{cliente}', Livewire\ClienteFicha::class)->name('clientes.ver');

    Route::get('/comprobantes/{tipo}/{id}', ComprobanteController::class)
        ->whereIn('tipo', ['nota', 'cobranza'])->whereNumber('id')->name('comprobantes.ver');

    Route::post('/trazabilidad/latido', [TrazabilidadController::class, 'latido'])->name('trazabilidad.latido');
    Route::post('/trazabilidad/evento', [TrazabilidadController::class, 'evento'])->name('trazabilidad.evento');

    // ---------------------------------------------------------- administracion
    Route::middleware('capacidad:puedeAdministrar')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/trazabilidad', Livewire\Admin\Trazabilidad::class)->name('trazabilidad');
        Route::livewire('/trazabilidad/sesion/{sesion}', Livewire\Admin\SesionDetalle::class)->name('trazabilidad.sesion');
        Route::get('/trazabilidad/exportar', ExportarTrazabilidadController::class)->name('trazabilidad.exportar');
        Route::livewire('/usuarios', Livewire\Admin\Usuarios::class)->name('usuarios');
        Route::livewire('/configuracion', Livewire\Admin\Configuracion::class)->name('configuracion');
        Route::livewire('/microsoft-365', Livewire\Admin\Integracion::class)->name('integracion');
    });
});
