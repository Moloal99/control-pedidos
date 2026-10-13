<?php

namespace App\Http\Controllers;

use App\Enums\AccionBitacora;
use App\Models\Bitacora;
use App\Models\Pagina;
use App\Models\Sesion;
use App\Support\LibroExcel;
use App\Support\Rastro;
use App\Support\Trazas;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Descarga de la trazabilidad en .xlsx (sesiones, pantallas, bitacora, por usuario). */
class ExportarTrazabilidadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $f = Trazas::filtros($request->only(['desde', 'hasta', 'usuario', 'accion', 'texto']));

        $sesiones = Trazas::sesiones($f)->with('usuario')->withCount(['paginas', 'asientos'])->latest('iniciada_at')->get();
        $paginas = Trazas::paginas($f)->with('usuario')->latest('entrada_at')->limit(10000)->get();
        $asientos = Trazas::bitacora($f)->with('usuario')->latest('created_at')->limit(10000)->get();

        Rastro::registrar(AccionBitacora::Exportar, 'Exportó la trazabilidad en XLSX', null, null, [
            'desde' => $f['desde']->toDateString(), 'hasta' => $f['hasta']->toDateString(), 'usuario' => $f['usuario'],
        ]);

        $libro = new LibroExcel;

        $libro->hoja('Por usuario',
            ['Usuario', 'Correo', 'Rol', 'Sesiones', 'Tiempo', 'Tiempo (s)', 'Pantallas', 'Acciones', 'Altas', 'Despachos', 'Cobros', 'Último acceso'],
            Trazas::porUsuario($f)->map(fn ($r) => [
                $r['usuario']->name, $r['usuario']->email, $r['usuario']->rol?->etiqueta(), $r['sesiones'],
                Sesion::formatear($r['segundos']), $r['segundos'], $r['pantallas'], $r['acciones'],
                $r['altas'], $r['despachos'], $r['cobros'], $r['ultima']?->format('d/m/Y H:i') ?? '—',
            ])->all(),
            [28, 32, 14, 10, 12, 11, 10, 10, 8, 10, 8, 18],
        );

        $libro->hoja('Sesiones',
            ['Usuario', 'Correo', 'Inicio', 'Fin', 'Duración', 'Duración (s)', 'Pantallas', 'Acciones', 'IP', 'Navegador', 'Equipo', 'Estado'],
            $sesiones->map(fn (Sesion $s) => [
                $s->usuario?->name ?? '—', $s->usuario?->email ?? '—',
                $s->iniciada_at->format('d/m/Y H:i:s'), $s->cerrada_at?->format('d/m/Y H:i:s') ?? 'En curso',
                Sesion::formatear($s->duracion()), $s->duracion(), $s->paginas_count, $s->asientos_count,
                (string) $s->ip, (string) $s->navegador, (string) $s->plataforma,
                $s->abierta() ? 'Abierta' : ($s->motivo_cierre === 'salida' ? 'Cerró sesión' : 'Expirada'),
            ])->all(),
            [26, 32, 20, 20, 14, 13, 10, 10, 16, 12, 12, 14],
        );

        $libro->hoja('Pantallas',
            ['Usuario', 'Pantalla', 'Entrada', 'Salida', 'Permanencia', 'Permanencia (s)', 'URL'],
            $paginas->map(fn (Pagina $p) => [
                $p->usuario?->name ?? '—', $p->titulo, $p->entrada_at->format('d/m/Y H:i:s'),
                $p->salida_at?->format('d/m/Y H:i:s') ?? '—', Sesion::formatear($p->segundos), $p->segundos, $p->url,
            ])->all(),
            [26, 40, 20, 20, 14, 16, 60],
        );

        $libro->hoja('Bitácora',
            ['Fecha', 'Usuario', 'Acción', 'Descripción', 'Documento', 'Entidad', 'ID', 'IP'],
            $asientos->map(fn (Bitacora $b) => [
                $b->created_at->format('d/m/Y H:i:s'), $b->usuario?->name ?? $b->usuario_nombre ?? '—',
                $b->accion->etiqueta(), $b->descripcion, (string) $b->referencia, (string) $b->entidad,
                $b->entidad_id ?? '', (string) $b->ip,
            ])->all(),
            [20, 26, 16, 70, 30, 14, 8, 16],
        );

        $nombre = 'trazabilidad-pedidos-'.$f['desde']->format('Ymd').'-'.$f['hasta']->format('Ymd').'.xlsx';

        return response($libro->contenido(), 200, LibroExcel::cabeceras($nombre));
    }
}
