<?php

namespace App\Servicios;

use App\Correo\Avisos;
use App\Enums\AccionBitacora;
use App\Enums\EstadoCobranza;
use App\Enums\FormaPagoCobranza;
use App\Enums\TipoPagoCobranza;
use App\Models\Cobranza;
use App\Models\NotaVenta;
use App\Models\User;
use App\SharePoint\Espejo;
use App\Support\Rastro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Pantalla Cobranza › «Guardar cobranza». No toca el saldo de la nota: la
 * cobranza queda «Pendiente de confirmación» hasta que Cobranzas la confirme.
 */
class RegistrarCobranza
{
    public function __construct(
        private readonly Espejo $espejo,
        private readonly Avisos $avisos,
    ) {}

    /**
     * @param  array{hubo_pago:mixed, tipo_pago:?string, monto:mixed, forma_pago:?string, fecha_proximo_pago:?string}  $datos
     */
    public function ejecutar(User $usuario, ?NotaVenta $nota, array $datos, ?UploadedFile $comprobante): Cobranza
    {
        Falla::si(! $nota, 'Seleccione una nota de venta');
        Falla::si(! $usuario->puedeCobrar(), 'Su rol no permite registrar cobranzas.');
        Falla::si($nota->anulada, 'La nota está anulada: no se puede cobrar.');
        Falla::si(! $usuario->veTodasLasNotas() && $nota->vendedor_codigo !== $usuario->codigo, 'Esa nota de venta no es suya.');

        $huboPago = filter_var($datos['hubo_pago'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tipo = TipoPagoCobranza::tryFrom((string) ($datos['tipo_pago'] ?? ''));
        $forma = FormaPagoCobranza::tryFrom((string) ($datos['forma_pago'] ?? ''));
        $proximo = filled($datos['fecha_proximo_pago'] ?? null) ? Carbon::parse($datos['fecha_proximo_pago'])->startOfDay() : null;

        // Disponible calculado en el servidor en el momento de guardar
        $disponible = $nota->disponibleParaCobrar();
        $saldo = (float) $nota->saldo_pendiente;
        $monto = $huboPago ? ($tipo === TipoPagoCobranza::Completo ? $disponible : round((float) ($datos['monto'] ?? 0), 2)) : 0.0;

        if ($huboPago) {
            Falla::si($disponible <= 0, 'Esta nota ya tiene cargado todo su saldo esperando aprobación. No se puede agregar otra cobranza hasta que el equipo de cobranza la revise.');
            Falla::si(! $tipo, 'Indique el tipo de pago', 'tipo_pago');
            Falla::si($monto <= 0, 'El monto debe ser mayor a cero', 'monto');
            Falla::si(
                $monto > $disponible + 0.004,
                'El monto no puede superar lo disponible para cobrar (Bs '.number_format($disponible, 2)
                    .($disponible < $saldo ? '. El saldo de la nota es Bs '.number_format($saldo, 2).', pero ya hay pagos cargados esperando aprobación' : '').')',
                'monto',
            );
            Falla::si(! $forma, 'Indique la forma de pago', 'forma_pago');
            Falla::si($forma->exigeComprobante() && ! $comprobante, 'Adjunte el comprobante '.$forma->conArticulo(), 'comprobante');
            Falla::si($proximo !== null && $proximo->isPast() && ! $proximo->isToday(), 'La fecha del próximo pago no puede ser anterior a hoy', 'fecha_proximo_pago');
        } else {
            Falla::si(! $proximo, 'Indique la fecha del próximo pago', 'fecha_proximo_pago');
            Falla::si($proximo->isPast() && ! $proximo->isToday(), 'La fecha del próximo pago no puede ser anterior a hoy', 'fecha_proximo_pago');
        }

        $archivo = $huboPago && $comprobante ? Comprobantes::guardar($comprobante, 'cobranzas', 'cobro-'.$nota->numero) : null;

        try {
            $cobranza = $this->espejo->operacion(function () use ($usuario, $nota, $huboPago, $tipo, $forma, $monto, $saldo, $proximo, $archivo) {
                $cobranza = Cobranza::query()->create([
                    'nota_id' => $nota->getKey(),
                    'nota_numero' => $nota->numero,
                    'proforma_clave' => $nota->proforma_clave,
                    'pedido_numero' => $nota->pedido_numero,
                    'cliente_id' => $nota->cliente_id,
                    'cliente_codigo' => $nota->cliente_codigo,
                    'cliente_nombre' => $nota->cliente_nombre,
                    // El vendedor de la nota, no quien cobra
                    'vendedor_codigo' => $nota->vendedor_codigo,
                    'vendedor_nombre' => $nota->vendedor_nombre,
                    'cargado_por_id' => $usuario->getKey(),
                    'cargado_por' => $nota->vendedor_codigo !== $usuario->codigo ? $usuario->name : '',
                    'fecha' => now(),
                    'hubo_pago' => $huboPago,
                    'tipo_pago' => $huboPago ? $tipo : null,
                    'monto' => $monto,
                    // Sin pago no hay forma de pago (la Power App arrastraba la anterior)
                    'forma_pago' => $huboPago ? $forma : null,
                    'fecha_proximo_pago' => $proximo,
                    'nota_total' => $nota->total,
                    'saldo_antes' => $saldo,
                    'saldo_despues' => max(0, round($saldo - $monto, 2)),
                    'estado' => EstadoCobranza::Pendiente,
                    'comprobante_nombre' => $archivo['nombre'] ?? null,
                    'comprobante_ruta' => $archivo['ruta'] ?? null,
                ]);
                $this->espejo->crear($cobranza);

                if ($archivo) {
                    $this->espejo->adjuntar($cobranza, $archivo['nombre'], $archivo['contenido']);
                }

                Rastro::registrar(
                    AccionBitacora::Cobrar,
                    $huboPago
                        ? "Registró un cobro {$tipo->value} de Bs ".number_format($monto, 2)." ({$forma->value}) sobre la nota {$nota->numero}"
                        : "Registró una gestión de cobro sin pago sobre la nota {$nota->numero}; próximo pago el ".$proximo->format('d/m/Y'),
                    'Cobranza', $cobranza->getKey(),
                    ['monto' => $monto, 'tipo' => $tipo?->value, 'forma' => $forma?->value, 'nota' => $nota->numero, 'proximo_pago' => $proximo?->toDateString()],
                    $nota->pedido_numero,
                    $usuario,
                );

                return $cobranza;
            });
        } catch (\Throwable $e) {
            Comprobantes::eliminar($archivo['ruta'] ?? null);

            throw $e;
        }

        if ($huboPago) {
            $this->avisos->cobranzaPendiente($cobranza, $usuario);
        }

        return $cobranza;
    }
}
