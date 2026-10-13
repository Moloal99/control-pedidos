<?php

namespace App\Servicios;

use App\Enums\AccionBitacora;
use App\Enums\FormaPago;
use App\Enums\MotivoVisita;
use App\Enums\TipoContacto;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Visita;
use App\SharePoint\Espejo;
use App\Support\Rastro;

/** Pantalla Visita › «Registrar visita». */
class RegistrarVisita
{
    public function __construct(private readonly Espejo $espejo) {}

    /**
     * @param  array{cliente_id:?int, tipo_contacto:?string, motivo:?string, forma_pago:?string, dias_credito:mixed, comentario:?string}  $datos
     */
    public function ejecutar(User $usuario, User $vendedor, array $datos): Visita
    {
        $cliente = isset($datos['cliente_id']) ? Cliente::query()->find($datos['cliente_id']) : null;
        $tipo = TipoContacto::tryFrom((string) ($datos['tipo_contacto'] ?? ''));
        $motivo = MotivoVisita::tryFrom((string) ($datos['motivo'] ?? ''));
        $forma = FormaPago::tryFrom((string) ($datos['forma_pago'] ?? ''));
        $dias = (int) ($datos['dias_credito'] ?? 0);
        $comentario = trim((string) ($datos['comentario'] ?? ''));

        Falla::si(! $cliente, 'Falta seleccionar el cliente', 'cliente_id');
        Falla::si(blank($vendedor->codigo), 'Su usuario no está registrado como vendedor; avise a Sistemas.');
        Falla::si(! $tipo, 'Falta elegir el tipo de contacto (Visita o Llamada)', 'tipo_contacto');
        Falla::si(! $motivo, 'Falta elegir el motivo de la visita', 'motivo');
        Falla::si($motivo === MotivoVisita::Pedido && ! $forma, 'Falta elegir la forma de pago (Crédito o Contado)', 'forma_pago');
        Falla::si($motivo === MotivoVisita::Pedido && $forma === FormaPago::Credito && $dias < 1, 'Falta indicar los días de crédito', 'dias_credito');
        Falla::si($motivo === MotivoVisita::Pedido && $forma === FormaPago::Credito && $dias > 99, 'Los días de crédito no pueden pasar de 99', 'dias_credito');
        Falla::si($comentario === '', 'Falta escribir el comentario de la visita', 'comentario');

        $esPedido = $motivo === MotivoVisita::Pedido;
        $aNombreDeOtro = $vendedor->getKey() !== $usuario->getKey();

        return $this->espejo->operacion(function () use ($usuario, $vendedor, $cliente, $tipo, $motivo, $forma, $dias, $comentario, $esPedido, $aNombreDeOtro) {
            $visita = Visita::query()->create([
                'titulo' => $cliente->nombre.' - '.now()->format('d/m/Y H:i'),
                'cliente_id' => $cliente->getKey(),
                'cliente_codigo' => $cliente->codigo,
                'cliente_nombre' => $cliente->nombre,
                'vendedor_id' => $vendedor->getKey(),
                'vendedor_codigo' => $vendedor->codigo,
                'vendedor_nombre' => $vendedor->name,
                'cargado_por_id' => $usuario->getKey(),
                // Quien lo tipeo. Vacio cuando es el propio vendedor (igual que SharePoint).
                'cargado_por' => $aNombreDeOtro ? $usuario->name : '',
                'motivo' => $motivo,
                'tipo_contacto' => $tipo,
                'forma_pago' => $esPedido ? $forma : null,
                'dias_credito' => $esPedido && $forma === FormaPago::Credito ? $dias : null,
                'comentario' => $comentario,
                'fecha' => now(),
            ]);

            $this->espejo->crear($visita);

            Rastro::registrar(
                AccionBitacora::Crear,
                ($tipo === TipoContacto::Llamada ? 'Registró una llamada' : 'Registró una visita').' a '.$cliente->nombre.' · '.$motivo->value
                    .($aNombreDeOtro ? ' (a nombre de '.$vendedor->name.')' : ''),
                'Visita', $visita->getKey(),
                ['motivo' => $motivo->value, 'tipo' => $tipo->value, 'forma_pago' => $forma?->value, 'dias' => $esPedido ? $dias : null, 'vendedor' => $vendedor->name],
                $cliente->codigo,
            );

            return $visita;
        });
    }
}
