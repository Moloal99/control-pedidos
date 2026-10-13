<?php

namespace App\Servicios;

use App\Enums\AccionBitacora;
use App\Enums\TipoCompetencia;
use App\Models\Cliente;
use App\Models\Relevamiento;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/** Pantalla Competencia › «Guardar relevamiento» y borrado de lo cargado hoy. */
class GuardarRelevamiento
{
    public function __construct(private readonly Espejo $espejo) {}

    /**
     * @param  array{cliente_id:?int, competidor:?string, tipo:?string, descripcion:?string, medida:?string, unidad:?string, precio:mixed, cantidad:mixed, observacion:?string}  $datos
     */
    public function ejecutar(User $usuario, User $vendedor, array $datos): Relevamiento
    {
        $cliente = isset($datos['cliente_id']) ? Cliente::query()->find($datos['cliente_id']) : null;
        $competidor = trim((string) ($datos['competidor'] ?? ''));
        // Primera letra mayuscula, resto minuscula (como la Power App)
        $competidor = $competidor === '' ? '' : mb_strtoupper(mb_substr($competidor, 0, 1)).mb_strtolower(mb_substr($competidor, 1));
        $tipo = TipoCompetencia::tryFrom((string) ($datos['tipo'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $unidad = trim((string) ($datos['unidad'] ?? ''));
        $precio = (float) ($datos['precio'] ?? 0);
        $cantidad = (float) ($datos['cantidad'] ?? 0);

        Falla::si(! $usuario->puedeVender(), 'Su rol no permite cargar relevamientos.');
        Falla::si(! $cliente, 'Elija el cliente', 'cliente_id');
        Falla::si($competidor === '', 'Escriba el competidor', 'competidor');
        Falla::si(! $tipo, 'Elija el tipo de producto', 'tipo');
        Falla::si($descripcion === '', 'Escriba la descripción del producto', 'descripcion');
        Falla::si($unidad === '', 'Elija la unidad', 'unidad');
        Falla::si($precio <= 0, 'El precio tiene que ser mayor a cero', 'precio');
        Falla::si($cantidad < 1, 'La cantidad tiene que ser 1 o más', 'cantidad');
        Falla::si(mb_strtoupper($unidad) === 'PZA' && floor($cantidad) != $cantidad, 'Las piezas se cuentan enteras', 'cantidad');

        return $this->espejo->operacion(function () use ($usuario, $vendedor, $cliente, $competidor, $tipo, $descripcion, $unidad, $precio, $cantidad, $datos) {
            $relevamiento = Relevamiento::query()->create([
                'titulo' => 'REL-'.$vendedor->codigo.'-'.now()->format('Ymd-His'),
                'fecha' => now(),
                'vendedor_id' => $vendedor->getKey(),
                'vendedor_codigo' => $vendedor->codigo,
                'vendedor_nombre' => $vendedor->name,
                'cargado_por_id' => $usuario->getKey(),
                'cliente_id' => $cliente->getKey(),
                'cliente_codigo' => $cliente->codigo,
                'cliente_nombre' => $cliente->nombre,
                'competidor' => $competidor,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'medida' => trim((string) ($datos['medida'] ?? '')) ?: null,
                'unidad' => mb_strtoupper($unidad),
                'precio' => $precio,
                'cantidad' => $cantidad,
                'observacion' => trim((string) ($datos['observacion'] ?? '')) ?: null,
            ]);
            $this->espejo->crear($relevamiento);

            Rastro::registrar(
                AccionBitacora::Crear,
                "Relevó a {$competidor} en {$cliente->nombre}: {$descripcion} a Bs ".number_format($precio, 2),
                'Relevamiento', $relevamiento->getKey(),
                ['tipo' => $tipo->value, 'precio' => $precio, 'cantidad' => $cantidad, 'unidad' => $unidad],
                $cliente->codigo,
                $usuario,
            );

            return $relevamiento;
        });
    }

    public function eliminar(User $usuario, Relevamiento $relevamiento): void
    {
        Falla::si(
            ! $usuario->esAdmin() && $relevamiento->cargado_por_id !== $usuario->getKey() && $relevamiento->vendedor_id !== $usuario->getKey(),
            'Solo puede eliminar sus propios relevamientos.',
        );
        Falla::si(! $usuario->esAdmin() && ! $relevamiento->fecha->isToday(), 'Solo se pueden eliminar los relevamientos del día.');

        $this->espejo->operacion(function () use ($usuario, $relevamiento) {
            $this->espejo->eliminar($relevamiento);
            $relevamiento->delete();

            Rastro::registrar(
                AccionBitacora::Eliminar,
                "Eliminó el relevamiento de {$relevamiento->competidor} · {$relevamiento->descripcion}",
                'Relevamiento', $relevamiento->getKey(),
                $relevamiento->only(['competidor', 'descripcion', 'precio', 'cliente_nombre']),
                $relevamiento->cliente_codigo,
                $usuario,
            );
        });
    }
}
