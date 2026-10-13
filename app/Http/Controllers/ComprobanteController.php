<?php

namespace App\Http\Controllers;

use App\Enums\AccionBitacora;
use App\Models\Cobranza;
use App\Models\NotaVenta;
use App\SharePoint\SharePoint;
use App\Support\Rastro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Muestra el comprobante de una nota o cobranza. Primero la copia local; si
 * no esta (elemento cargado desde la Power App), se trae de SharePoint.
 */
class ComprobanteController extends Controller
{
    public function __invoke(Request $request, string $tipo, int $id, SharePoint $sharePoint): Response
    {
        $usuario = $request->user();
        $modelo = $tipo === 'nota' ? NotaVenta::query()->findOrFail($id) : Cobranza::query()->findOrFail($id);

        $pedido = $tipo === 'nota' ? $modelo->pedido : $modelo->nota?->pedido;
        $permitido = $usuario->veTodosLosPedidos() || $usuario->veTodasLasNotas() || $usuario->puedeConfirmarCobranzas()
            || ($pedido && $usuario->puedeVerPedido($pedido))
            || $modelo->vendedor_codigo === $usuario->codigo;

        abort_unless($permitido, 403);

        $disco = Storage::disk(config('pedidos.comprobantes.disco'));
        $nombre = $modelo->comprobante_nombre ?: 'comprobante';

        if ($modelo->comprobante_ruta && $disco->exists($modelo->comprobante_ruta)) {
            $contenido = $disco->get($modelo->comprobante_ruta);
        } elseif ($modelo->sp_id && $sharePoint->habilitado() && ($adjunto = $sharePoint->descargarAdjunto($modelo::listaSharePoint(), (int) $modelo->sp_id))) {
            $contenido = $adjunto['contenido'];
            $nombre = $adjunto['nombre'];
            // Se guarda la copia para la proxima vez
            $ruta = 'comprobantes/'.($tipo === 'nota' ? 'notas' : 'cobranzas').'/sp-'.$modelo->sp_id.'-'.$nombre;
            $disco->put($ruta, $contenido);
            $modelo->forceFill(['comprobante_ruta' => $ruta, 'comprobante_nombre' => $nombre])->saveQuietly();
        } else {
            abort(404, 'Este documento no tiene comprobante adjunto.');
        }

        Rastro::registrar(
            AccionBitacora::Ver,
            'Abrió el comprobante de '.($tipo === 'nota' ? "la nota {$modelo->numero}" : "la cobranza de la nota {$modelo->nota_numero}"),
            $tipo === 'nota' ? 'NotaVenta' : 'Cobranza', $modelo->getKey(), null,
            $modelo->pedido_numero,
        );

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido) ?: 'application/octet-stream';

        return response($contenido, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
