<?php

namespace App\Support;

use App\Enums\AccionBitacora;
use App\Models\Bitacora;
use App\Models\Pagina;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consultas y metricas del panel de trazabilidad (portadas del portal
 * central). Las agrupaciones se hacen en PHP para que el mismo codigo sirva
 * en SQLite y en MariaDB.
 */
class Trazas
{
    /**
     * @param  array{desde?:mixed, hasta?:mixed, usuario?:mixed, accion?:mixed, texto?:mixed}  $entrada
     * @return array{desde:Carbon, hasta:Carbon, usuario:?int, accion:?string, texto:?string}
     */
    public static function filtros(array $entrada): array
    {
        $desde = filled($entrada['desde'] ?? null) ? Carbon::parse($entrada['desde']) : now()->subDays(29);
        $hasta = filled($entrada['hasta'] ?? null) ? Carbon::parse($entrada['hasta']) : now();

        return [
            'desde' => $desde->copy()->startOfDay(),
            'hasta' => $hasta->copy()->endOfDay(),
            'usuario' => ((int) ($entrada['usuario'] ?? 0)) ?: null,
            'accion' => AccionBitacora::tryFrom((string) ($entrada['accion'] ?? ''))?->value,
            'texto' => trim((string) ($entrada['texto'] ?? '')) ?: null,
        ];
    }

    /** @return Builder<Sesion> */
    public static function sesiones(array $f): Builder
    {
        return Sesion::query()
            ->whereBetween('iniciada_at', [$f['desde'], $f['hasta']])
            ->when($f['usuario'], fn ($q, $id) => $q->where('user_id', $id));
    }

    /** @return Builder<Pagina> */
    public static function paginas(array $f): Builder
    {
        return Pagina::query()
            ->whereBetween('entrada_at', [$f['desde'], $f['hasta']])
            ->when($f['usuario'], fn ($q, $id) => $q->where('user_id', $id));
    }

    /** @return Builder<Bitacora> */
    public static function bitacora(array $f): Builder
    {
        return Bitacora::query()
            ->whereBetween('created_at', [$f['desde'], $f['hasta']])
            ->when($f['usuario'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['accion'], fn ($q, $a) => $q->where('accion', $a))
            ->when($f['texto'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('descripcion', 'like', "%{$t}%")
                ->orWhere('referencia', 'like', "%{$t}%")
                ->orWhere('usuario_nombre', 'like', "%{$t}%")));
    }

    /**
     * @return array<string, mixed>
     */
    public static function metricas(array $f): array
    {
        $sesiones = self::sesiones($f)->with('usuario')->get();
        $paginas = self::paginas($f)->with('usuario')->get();
        $asientos = self::bitacora(array_merge($f, ['accion' => null, 'texto' => null]))->get();
        $segundos = $sesiones->map(fn (Sesion $s) => $s->duracion());

        $dias = collect();
        $cursor = $f['desde']->copy()->startOfDay();
        $tope = min($f['hasta']->copy()->startOfDay(), now()->startOfDay());
        $porDia = $sesiones->groupBy(fn (Sesion $s) => $s->iniciada_at->toDateString());
        $accionesDia = $asientos->groupBy(fn (Bitacora $b) => $b->created_at->toDateString());

        while ($cursor->lte($tope) && $dias->count() < 62) {
            $clave = $cursor->toDateString();
            $delDia = $porDia->get($clave, collect());

            $dias->push([
                'fecha' => $cursor->copy(),
                'sesiones' => $delDia->count(),
                'usuarios' => $delDia->pluck('user_id')->unique()->count(),
                'minutos' => (int) round($delDia->sum(fn (Sesion $s) => $s->duracion()) / 60),
                'acciones' => $accionesDia->get($clave, collect())->count(),
            ]);

            $cursor->addDay();
        }

        return [
            'sesiones' => $sesiones->count(),
            'usuarios' => $sesiones->pluck('user_id')->unique()->count(),
            'paginas' => $paginas->count(),
            'acciones' => $asientos->count(),
            'segundos_total' => (int) $segundos->sum(),
            'promedio_sesion' => (int) ($segundos->avg() ?? 0),
            'abiertas' => $sesiones->filter(fn (Sesion $s) => $s->abierta())->count(),
            'dias' => $dias,
            'horas' => collect(range(6, 22))->map(fn (int $h) => [
                'hora' => $h,
                'total' => $paginas->filter(fn (Pagina $p) => (int) $p->entrada_at->format('G') === $h)->count(),
            ]),
            'pantallas_top' => $paginas->groupBy(fn (Pagina $p) => str($p->titulo)->before(' · ')->toString() ?: 'Sin nombre')
                ->map(fn (Collection $g, string $e) => ['etiqueta' => $e, 'visitas' => $g->count(), 'segundos' => (int) $g->sum('segundos')])
                ->sortByDesc('segundos')->take(8)->values(),
            'acciones_top' => $asientos->groupBy(fn (Bitacora $b) => $b->accion->value)
                ->map(fn (Collection $g, string $c) => ['etiqueta' => AccionBitacora::from($c)->etiqueta(), 'tono' => AccionBitacora::from($c)->tono(), 'total' => $g->count()])
                ->sortByDesc('total')->values(),
        ];
    }

    /**
     * Resumen por usuario: quien trabajo, cuanto y que hizo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function porUsuario(array $f): Collection
    {
        $sesiones = self::sesiones(array_merge($f, ['usuario' => null]))->get()->groupBy('user_id');
        $asientos = self::bitacora(array_merge($f, ['usuario' => null, 'accion' => null, 'texto' => null]))->get()->groupBy('user_id');
        $paginas = self::paginas(array_merge($f, ['usuario' => null]))->get()->groupBy('user_id');

        $ids = $sesiones->keys()->merge($asientos->keys())->filter()->unique();

        return User::query()->whereIn('id', $ids)->get()->map(function (User $u) use ($sesiones, $asientos, $paginas) {
            $suyas = $sesiones->get($u->id, collect());
            $acciones = $asientos->get($u->id, collect());
            $cuenta = fn (AccionBitacora ...$a) => $acciones->filter(fn (Bitacora $b) => in_array($b->accion, $a, true))->count();

            return [
                'usuario' => $u,
                'sesiones' => $suyas->count(),
                'segundos' => (int) $suyas->sum(fn (Sesion $s) => $s->duracion()),
                'pantallas' => $paginas->get($u->id, collect())->count(),
                'acciones' => $acciones->count(),
                'altas' => $cuenta(AccionBitacora::Crear),
                'despachos' => $cuenta(AccionBitacora::Despachar),
                'cobros' => $cuenta(AccionBitacora::Cobrar, AccionBitacora::Confirmar),
                'ultima' => $suyas->max('ultima_at'),
                'en_linea' => $suyas->contains(fn (Sesion $s) => $s->abierta() && $s->ultima_at->gt(now()->subMinutes(5))),
            ];
        })->sortByDesc('segundos')->values();
    }
}
