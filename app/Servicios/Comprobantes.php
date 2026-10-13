<?php

namespace App\Servicios;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Comprobantes adjuntos (nota de venta, cobranza). Se guarda una copia local
 * para verlos al instante y, con SharePoint activo, se suben como «Datos
 * adjuntos» del elemento, igual que hacia la Power App.
 */
class Comprobantes
{
    /**
     * @return array{nombre:string, ruta:string, contenido:string}
     */
    public static function guardar(UploadedFile $archivo, string $carpeta, string $prefijo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension() ?: 'bin');
        $nombre = Str::slug($prefijo).'-'.now()->format('Ymd-His').'.'.$extension;
        $ruta = $archivo->storeAs('comprobantes/'.$carpeta, $nombre, config('pedidos.comprobantes.disco'));

        return [
            'nombre' => $nombre,
            'ruta' => $ruta,
            'contenido' => (string) file_get_contents($archivo->getRealPath()),
        ];
    }

    public static function eliminar(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk(config('pedidos.comprobantes.disco'))->delete($ruta);
        }
    }

    /**
     * @return list<string>
     */
    public static function reglas(bool $obligatorio = true): array
    {
        return array_values(array_filter([
            $obligatorio ? 'required' : 'nullable',
            'file',
            'max:'.config('pedidos.comprobantes.max_kb'),
            'mimes:'.config('pedidos.comprobantes.tipos'),
        ]));
    }
}
