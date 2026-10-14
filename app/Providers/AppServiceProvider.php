<?php

namespace App\Providers;

use App\Correo\TransporteGraph;
use App\SharePoint\Espejo;
use App\SharePoint\GraphSharePoint;
use App\SharePoint\SharePoint;
use App\SharePoint\SharePointNulo;
use App\SharePoint\TokenMicrosoft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenMicrosoft::class);

        // Modo «sharepoint» con credenciales completas → Graph; si no, demo local
        $this->app->singleton(SharePoint::class, function ($app) {
            $token = $app->make(TokenMicrosoft::class);

            return config('pedidos.modo') === 'sharepoint' && $token->configurado() && filled(config('microsoft.sharepoint.host'))
                ? new GraphSharePoint($token)
                : new SharePointNulo;
        });

        $this->app->scoped(Espejo::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('es');

        Mail::extend('graph', fn () => new TransporteGraph($this->app->make(TokenMicrosoft::class)));

        // Detras del proxy de Render / Cloudflare: los enlaces y Livewire van por https
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
