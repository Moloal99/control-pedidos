<?php

namespace App\SharePoint;

use App\Enums\EstadoCobranza;
use App\Enums\EstadoDespacho;
use App\Enums\EstadoPago;
use App\Enums\EstadoProforma;
use App\Enums\MotivoVisita;
use App\Enums\Rol;
use App\Enums\TipoCompetencia;
use App\Enums\TipoContacto;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\NotaLinea;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\Relevamiento;
use App\Models\Sincronizacion;
use App\Models\User;
use App\Models\Visita;
use App\Support\Almacenes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Trae a la base local lo que cambio en SharePoint. Asi las pantallas leen
 * rapido (sin limites de delegacion) y lo que se cargue desde la Power App,
 * Power BI u otra herramienta durante la transicion tambien aparece aqui.
 *
 * Las relaciones se resuelven por las mismas claves de texto que usa
 * SharePoint: PEDIDO_NUMERO, PROFORMA_CLAVE y NOTA_NUMERO.
 */
class Sincronizador
{
    /** Orden de dependencias: maestros, luego cabeceras, luego detalles. */
    public const MODELOS = [
        User::class,
        Cliente::class,
        Producto::class,
        Visita::class,
        Pedido::class,
        Proforma::class,
        PedidoLinea::class,
        NotaVenta::class,
        NotaLinea::class,
        Cobranza::class,
        Relevamiento::class,
    ];

    public function __construct(private readonly SharePoint $sharePoint) {}

    /**
     * @param  list<class-string<Model>>|null  $modelos
     * @return array<string, array{filas:int, omitidas:int, borradas:int, error:?string}>
     */
    public function ejecutar(?array $modelos = null, bool $completa = false): array
    {
        $resumen = [];

        foreach ($modelos ?? self::MODELOS as $modelo) {
            $lista = $modelo::listaSharePoint();
            $estado = Sincronizacion::query()->firstOrCreate(['lista' => $lista]);
            $inicio = now();
            $filas = 0;
            $omitidas = 0;
            $borradas = 0;
            $marca = $completa ? null : $estado->marca_at?->copy()->subMinutes(2);
            $ultimaModificacion = $estado->marca_at;

            try {
                foreach ($this->sharePoint->cambios($lista, $marca) as $campos) {
                    if ($this->importar($modelo, $campos)) {
                        $filas++;
                    } else {
                        $omitidas++;
                    }

                    if (! empty($campos['Modified'])) {
                        $modificado = Carbon::parse($campos['Modified']);
                        $ultimaModificacion = $ultimaModificacion === null || $modificado->gt($ultimaModificacion) ? $modificado : $ultimaModificacion;
                    }
                }

                if ($completa) {
                    $borradas = $this->purgar($modelo, $lista);
                }

                $estado->update([
                    'ultima_at' => $inicio,
                    'marca_at' => $ultimaModificacion,
                    'filas' => $filas,
                    'estado' => 'ok',
                    'error' => null,
                ]);

                $resumen[$lista] = compact('filas', 'omitidas', 'borradas') + ['error' => null];
            } catch (Throwable $e) {
                report($e);
                $estado->update(['ultima_at' => $inicio, 'estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
                $resumen[$lista] = compact('filas', 'omitidas', 'borradas') + ['error' => $e->getMessage()];
            }
        }

        return $resumen;
    }

    /**
     * @param  class-string<Model>  $clase
     * @param  array<string, mixed>  $campos
     */
    public function importar(string $clase, array $campos): bool
    {
        $spId = (int) ($campos['id'] ?? 0);

        if ($spId === 0) {
            return false;
        }

        $atributos = $clase::desdeSharePoint($campos);
        $atributos = $this->completar($clase, $atributos);

        if ($atributos === null) {
            return false;
        }

        /** @var Model $modelo */
        $modelo = $clase::query()->firstOrNew(['sp_id' => $spId]);

        // El usuario puede existir ya por correo (alta local previa)
        if ($clase === User::class && ! $modelo->exists && ! empty($atributos['email'])) {
            $modelo = User::query()->where('email', $atributos['email'])->first() ?? $modelo;
        }

        $modelo->forceFill($atributos + ['sp_id' => $spId]);

        if ($modelo->isDirty()) {
            // save (no saveQuietly): clientes y productos recalculan su columna de busqueda
            $modelo->save();
        }

        return true;
    }

    /**
     * Completa claves locales y valores obligatorios. null = fila inutilizable.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>|null
     */
    private function completar(string $clase, array $a): ?array
    {
        $pedidoId = fn (?string $numero) => $numero ? Pedido::query()->where('numero', $numero)->value('id') : null;
        $usuarioId = fn (?string $codigo) => $codigo ? User::query()->where('codigo', $codigo)->value('id') : null;

        switch ($clase) {
            case User::class:
                if (empty($a['email'])) {
                    return null;
                }
                $a['email'] = mb_strtolower(trim((string) $a['email']));
                $a['name'] = $a['name'] ?: $a['email'];
                $a['rol'] = $a['rol'] ?? Rol::Vendedor;
                $a['carga_por_otros'] = (bool) ($a['carga_por_otros'] ?? false);
                $a['despacha'] = (bool) ($a['despacha'] ?? false);
                break;

            case Cliente::class:
                if (empty($a['codigo']) && empty($a['nombre'])) {
                    return null;
                }
                $a['codigo'] = (string) ($a['codigo'] ?? '');
                $a['nombre'] = (string) ($a['nombre'] ?? $a['codigo']);
                break;

            case Producto::class:
                if (empty($a['codigo'])) {
                    return null;
                }
                $a['descripcion'] = (string) ($a['descripcion'] ?? $a['codigo']);
                $a['peso_promedio'] ??= 0;
                break;

            case Visita::class:
                if (empty($a['fecha'])) {
                    return null;
                }
                $a['titulo'] = $a['titulo'] ?? (($a['cliente_nombre'] ?? 'Visita').' - '.$a['fecha']->format('d/m/Y H:i'));
                $a['vendedor_id'] ??= $usuarioId($a['vendedor_codigo'] ?? null);
                $a['motivo'] ??= MotivoVisita::Rutina;
                $a['tipo_contacto'] ??= TipoContacto::Visita;
                break;

            case Pedido::class:
                if (empty($a['numero'])) {
                    return null;
                }
                $a['fecha'] ??= now();
                $a['vendedor_id'] ??= $usuarioId($a['vendedor_codigo'] ?? null);
                $a['estado_despacho'] ??= EstadoDespacho::SinProforma;
                $a['estado_pago'] ??= EstadoPago::Pendiente;
                $a['total'] ??= 0;
                $a['total_pagado'] ??= 0;
                $a['saldo_pendiente'] ??= $a['total'];
                break;

            case Proforma::class:
                $a['pedido_id'] = $pedidoId($a['pedido_numero'] ?? null);
                if (! $a['pedido_id'] || empty($a['clave'])) {
                    return null;
                }
                $a['almacen_codigo'] = (string) str($a['clave'])->afterLast('-');
                $a['almacen'] ??= Almacenes::porCodigo($a['almacen_codigo'])->nombre;
                $a['fecha'] ??= now();
                $a['total'] ??= 0;
                $a['total_despachado'] ??= 0;
                $a['estado'] ??= EstadoProforma::SinNumero;
                break;

            case PedidoLinea::class:
                $a['pedido_id'] = $pedidoId($a['pedido_numero'] ?? null);
                if (! $a['pedido_id'] || empty($a['producto_codigo'])) {
                    return null;
                }
                $a['proforma_id'] = Proforma::query()->where('clave', $a['proforma_clave'] ?? '')->value('id');
                $a['proforma_clave'] ??= '';
                $a['almacen'] ??= '';
                $a['producto_descripcion'] ??= $a['producto_codigo'];
                $a['producto_peso_promedio'] ??= 0;
                $a['cantidad'] ??= 0;
                $a['precio_unitario'] ??= 0;
                $a['cantidad_despachada'] ??= 0;
                break;

            case NotaVenta::class:
                if (empty($a['numero'])) {
                    return null;
                }
                $a['pedido_id'] = $pedidoId($a['pedido_numero'] ?? null);
                $a['proforma_id'] = Proforma::query()->where('clave', $a['proforma_clave'] ?? '')->value('id');
                $a['proforma_clave'] ??= '';
                $a['pedido_numero'] ??= '';
                $a['fecha'] ??= now();
                $a['total'] ??= 0;
                $a['total_pagado'] ??= 0;
                $a['saldo_pendiente'] ??= $a['total'];
                $a['estado_pago'] ??= EstadoPago::Pendiente;
                $a['anulada'] = (bool) ($a['anulada'] ?? false);
                break;

            case NotaLinea::class:
                $nota = NotaVenta::query()
                    ->where('numero', $a['nota_numero'] ?? '')
                    ->when(! empty($a['proforma_clave']), fn ($q) => $q->where('proforma_clave', $a['proforma_clave']))
                    ->value('id');
                if (! $nota) {
                    return null;
                }
                $a['nota_id'] = $nota;
                $a['pedido_linea_id'] = PedidoLinea::query()
                    ->where('proforma_clave', $a['proforma_clave'] ?? '')
                    ->where('producto_codigo', $a['producto_codigo'] ?? '')
                    ->value('id');
                $a['proforma_clave'] ??= '';
                $a['producto_codigo'] ??= '';
                $a['producto_descripcion'] ??= $a['producto_codigo'];
                $a['cantidad'] ??= 0;
                $a['precio_unitario'] ??= 0;
                $a['producto_peso_promedio'] ??= 0;
                break;

            case Cobranza::class:
                if (empty($a['nota_numero'])) {
                    return null;
                }
                $a['nota_id'] = NotaVenta::query()
                    ->where('numero', $a['nota_numero'])
                    ->when(! empty($a['pedido_numero']), fn ($q) => $q->where('pedido_numero', $a['pedido_numero']))
                    ->value('id');
                $a['fecha'] ??= now();
                $a['hubo_pago'] = (bool) ($a['hubo_pago'] ?? false);
                $a['monto'] ??= 0;
                $a['nota_total'] ??= 0;
                $a['saldo_antes'] ??= 0;
                $a['saldo_despues'] ??= 0;
                $a['estado'] ??= EstadoCobranza::Pendiente;
                $a['cargado_por_id'] = ! empty($a['cargado_por'])
                    ? User::query()->where('name', $a['cargado_por'])->value('id')
                    : null;
                break;

            case Relevamiento::class:
                if (empty($a['fecha'])) {
                    return null;
                }
                $a['titulo'] ??= 'REL';
                $a['vendedor_id'] ??= $usuarioId($a['vendedor_codigo'] ?? null);
                $a['competidor'] ??= '';
                $a['tipo'] ??= TipoCompetencia::Otro;
                $a['descripcion'] ??= '';
                $a['precio'] ??= 0;
                $a['cantidad'] ??= 0;
                break;
        }

        return $a;
    }

    /** Borra localmente lo que ya no existe en SharePoint (solo en pasada completa). */
    private function purgar(string $clase, string $lista): int
    {
        $vigentes = $this->sharePoint->ids($lista);

        if ($vigentes === [] || $clase === User::class) {
            // Lista vacia = probable error de permisos: no se borra nada.
            // Los usuarios nunca se borran (tienen trazabilidad): se desactivan.
            if ($clase === User::class && $vigentes !== []) {
                User::query()->whereNotNull('sp_id')->whereNotIn('sp_id', $vigentes)->update(['activo' => false]);
            }

            return 0;
        }

        return $clase::query()->whereNotNull('sp_id')->whereNotIn('sp_id', $vigentes)->delete();
    }
}
