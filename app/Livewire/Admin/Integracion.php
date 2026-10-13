<?php

namespace App\Livewire\Admin;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Livewire\Pantalla;
use App\Models\Correo;
use App\Models\Sincronizacion;
use App\SharePoint\SharePoint;
use App\SharePoint\Sincronizador;
use App\SharePoint\TokenMicrosoft;
use App\Support\Rastro;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Throwable;

/** Estado de la conexion con Microsoft 365: listas, sincronizacion y bandeja de correos. */
#[Title('Microsoft 365')]
class Integracion extends Pantalla
{
    use WithPagination;

    public string $estadoCorreo = '';

    public string $correoPrueba = '';

    public function mount(): void
    {
        $this->correoPrueba = $this->usuario()->email;
    }

    public function sincronizar(bool $completa = false): void
    {
        $sincronizador = app(Sincronizador::class);

        if (config('pedidos.modo') !== 'sharepoint') {
            $this->avisar('alerta', 'El sistema está en modo demostración: no hay SharePoint conectado.');

            return;
        }

        $resumen = $sincronizador->ejecutar(null, $completa);
        $errores = collect($resumen)->filter(fn ($r) => $r['error']);

        Rastro::registrar(AccionBitacora::Sincronizar, ($completa ? 'Sincronización completa' : 'Sincronización').' manual con SharePoint', null, null, $resumen);

        $errores->isEmpty()
            ? $this->avisar('exito', 'Sincronización terminada: '.collect($resumen)->sum('filas').' elemento(s) actualizados.')
            : $this->avisar('error', 'Hubo errores en '.$errores->keys()->implode(', ').'. Revise el detalle.');
    }

    public function reintentar(int $id, Avisos $avisos): void
    {
        $avisos->reintentar(Correo::query()->findOrFail($id));
        $this->avisar('info', 'El correo volvió a la cola de envío.');
    }

    public function probarCorreo(Avisos $avisos): void
    {
        try {
            $avisos->prueba($this->correoPrueba);
            $this->avisar('exito', "Correo de prueba enviado a {$this->correoPrueba}");
        } catch (Throwable $e) {
            $this->avisar('error', 'No se pudo enviar: '.$e->getMessage());
        }
    }

    public function render()
    {
        $token = app(TokenMicrosoft::class);
        $sharePoint = app(SharePoint::class);

        return view('pantallas.admin.integracion', [
            'modo' => config('pedidos.modo'),
            'conectado' => $sharePoint->habilitado(),
            'credenciales' => $token->configurado(),
            'certificado' => $token->tieneCertificado(),
            'mailer' => config('mail.default'),
            'listas' => Sincronizacion::query()->orderBy('lista')->get(),
            'correos' => Correo::query()->when($this->estadoCorreo !== '', fn ($q) => $q->where('estado', $this->estadoCorreo))->latest()->paginate(15),
            'fallidos' => Correo::query()->where('estado', 'fallido')->count(),
            'sitio' => config('microsoft.sharepoint.host').config('microsoft.sharepoint.sitio'),
        ]);
    }
}
