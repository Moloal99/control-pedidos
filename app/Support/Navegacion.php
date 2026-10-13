<?php

namespace App\Support;

use App\Enums\EstadoCobranza;
use App\Enums\EstadoDespacho;
use App\Models\Cobranza;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Menu segun capacidades. Corrige el «encierro» de la Power App, donde
 * almacen, cobranzas y cobrador no tenian barra de navegacion.
 */
class Navegacion
{
    /**
     * @return list<array{titulo:string, items:list<array{ruta:string, etiqueta:string, icono:string, insignia?:int|null, movil?:bool}>}>
     */
    public static function para(User $u): array
    {
        $insignias = self::insignias($u);
        $grupos = [];

        $operacion = [['ruta' => 'inicio', 'etiqueta' => 'Inicio', 'icono' => 'inicio', 'movil' => true]];

        if ($u->puedeVender()) {
            $operacion[] = ['ruta' => 'visita', 'etiqueta' => 'Visita', 'icono' => 'ubicacion', 'movil' => true];
            $operacion[] = ['ruta' => 'pedido', 'etiqueta' => 'Pedido', 'icono' => 'carrito', 'insignia' => count(VentaEnCurso::carrito()) ?: null, 'movil' => true];
        }

        if ($u->puedeCobrar()) {
            $operacion[] = ['ruta' => 'cobranza', 'etiqueta' => 'Cobranza', 'icono' => 'billete', 'movil' => true];
        }

        if ($u->puedeVender()) {
            $operacion[] = ['ruta' => 'competencia', 'etiqueta' => 'Competencia', 'icono' => 'etiqueta'];
        }

        $grupos[] = ['titulo' => 'Operación', 'items' => $operacion];

        $seguimiento = [];

        if ($u->veTabPedidos() || $u->veTodosLosPedidos()) {
            $seguimiento[] = ['ruta' => 'pedidos.index', 'etiqueta' => $u->veTodosLosPedidos() ? 'Pedidos' : 'Mis pedidos', 'icono' => 'caja', 'insignia' => $insignias['sin_proforma'], 'movil' => ! $u->puedeVender()];
        }

        if ($u->veTabCobranzas()) {
            $seguimiento[] = ['ruta' => 'cobranzas.confirmar', 'etiqueta' => 'Cobranzas por confirmar', 'icono' => 'recibo', 'insignia' => $insignias['por_confirmar'], 'movil' => true];
        }

        if ($seguimiento !== []) {
            $grupos[] = ['titulo' => 'Seguimiento', 'items' => $seguimiento];
        }

        if ($u->puedeAdministrar()) {
            $grupos[] = ['titulo' => 'Administración', 'items' => [
                ['ruta' => 'admin.trazabilidad', 'etiqueta' => 'Trazabilidad', 'icono' => 'huella'],
                ['ruta' => 'admin.usuarios', 'etiqueta' => 'Usuarios', 'icono' => 'usuarios'],
                ['ruta' => 'admin.configuracion', 'etiqueta' => 'Configuración', 'icono' => 'engranaje'],
                ['ruta' => 'admin.integracion', 'etiqueta' => 'Microsoft 365', 'icono' => 'nube'],
            ]];
        }

        return $grupos;
    }

    /**
     * Elementos para la barra inferior del celular (maximo 5).
     *
     * @return list<array<string, mixed>>
     */
    public static function movil(User $u): array
    {
        return collect(self::para($u))->flatMap(fn ($g) => $g['items'])->filter(fn ($i) => $i['movil'] ?? false)->take(5)->values()->all();
    }

    /**
     * @return array{sin_proforma:int|null, por_confirmar:int|null}
     */
    public static function insignias(User $u): array
    {
        return Cache::remember('insignias:'.$u->getKey(), 30, fn () => [
            'sin_proforma' => ($u->esAlmacen() || $u->esAdmin())
                ? (Pedido::query()->where('estado_despacho', EstadoDespacho::SinProforma->value)->where('fecha', '>=', now()->subDays(90))->count() ?: null)
                : null,
            'por_confirmar' => $u->puedeConfirmarCobranzas()
                ? (Cobranza::query()->where('hubo_pago', true)->where('estado', EstadoCobranza::Pendiente->value)->count() ?: null)
                : null,
        ]);
    }
}
