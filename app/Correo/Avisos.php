<?php

namespace App\Correo;

use App\Enums\AccionBitacora;
use App\Jobs\EnviarAviso;
use App\Models\Ajuste;
use App\Models\Almacen;
use App\Models\Cobranza;
use App\Models\Correo;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\User;
use App\Support\Almacenes;
use App\Support\Rastro;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Los seis avisos de la Power App, con sus mismos destinatarios. Cada envio
 * queda registrado en `correos` y se manda en segundo plano con reintentos.
 * Un correo que falla NUNCA revierte la operacion de negocio.
 */
class Avisos
{
    // ------------------------------------------------------------- eventos

    public function nuevoPedido(Pedido $pedido, User $usuario): void
    {
        $pedido->load(['lineas', 'cliente', 'visita', 'vendedor']);
        $vendedor = $pedido->vendedor;

        $para = [
            // El vendedor que despacha sus propios pedidos no necesita al almacen
            $vendedor?->vendedorDespacha() ? null : Ajuste::valor('correo_rol_almacen'),
            $vendedor?->email,
            $usuario->getKey() !== $vendedor?->getKey() ? $usuario->email : null,
        ];

        $grupos = $pedido->lineas->groupBy('almacen')->sortKeys()->map(fn ($lineas, $almacen) => [
            'almacen' => $almacen,
            'kg' => $lineas->sum(fn (PedidoLinea $l) => $l->peso()),
            'lineas' => $lineas->map(fn (PedidoLinea $l) => [
                'codigo' => $l->producto_codigo,
                'descripcion' => $l->producto_descripcion,
                'cantidad' => (float) $l->cantidad,
                'unidad' => $l->producto_unidad,
                'precio' => (float) $l->precio_unitario,
                'subtotal' => $l->subtotal(),
            ])->values()->all(),
        ])->values()->all();

        $this->enviar('nuevo_pedido', "Nuevo pedido {$pedido->numero} — {$pedido->cliente_nombre}", 'nuevo-pedido', [
            'numero' => $pedido->numero,
            'fecha' => $pedido->fecha->format('d/m/Y H:i'),
            'cliente_codigo' => $pedido->cliente_codigo,
            'cliente' => $pedido->cliente_nombre,
            'direccion' => $pedido->cliente?->direccionLegible(),
            'telefono' => $pedido->cliente?->telefonoLegible(),
            'vendedor' => trim($pedido->vendedor_codigo.' — '.$pedido->vendedor_nombre),
            'cargado_por' => $pedido->cargado_por ?: null,
            'tipo_contacto' => $pedido->tipo_contacto?->value,
            'forma_pago' => $pedido->forma_pago?->value,
            'dias' => $pedido->dias_credito,
            'vence' => $pedido->fecha_vencimiento?->format('d/m/Y'),
            'comentario' => $pedido->visita?->comentario,
            'grupos' => $grupos,
            'kg' => $pedido->pesoTotal(),
            'total' => (float) $pedido->total,
            'enlace' => route('pedidos.ver', $pedido),
        ], $para, $pedido->numero, $usuario);
    }

    public function cobranzaPendiente(Cobranza $cobranza, User $usuario): void
    {
        $this->enviar(
            'cobranza_pendiente',
            "Cobranza pendiente · Nota {$cobranza->nota_numero} · {$cobranza->cliente_nombre} · Bs ".number_format((float) $cobranza->monto, 2),
            'cobranza-pendiente',
            $this->datosCobranza($cobranza) + ['registro' => $usuario->name, 'enlace' => route('cobranzas.confirmar', ['cobranza' => $cobranza->getKey()])],
            [Ajuste::valor('correo_rol_cobranzas')],
            $cobranza->pedido_numero,
            $usuario,
        );
    }

    public function notaAnulada(NotaVenta $nota, User $usuario): void
    {
        $nota->load('proforma');
        $almacen = $nota->proforma ? Almacenes::porCodigo($nota->proforma->almacen_codigo) : null;

        $encargados = Ajuste::valor('anulacion_avisa_todos_los_almacenes', '1') === '1'
            ? Almacenes::todos()->flatMap(fn (Almacen $a) => $a->listaCorreos())->all()
            : ($almacen?->listaCorreos() ?? []);

        $para = array_merge(
            [$this->correoVendedor($nota->vendedor_codigo), $usuario->email, Ajuste::valor('correo_rol_almacen')],
            Ajuste::correos('correos_anulacion_fijos'),
            $encargados,
        );

        $this->enviar('nota_anulada', "Nota de venta anulada · {$nota->numero} · {$nota->cliente_nombre}", 'nota-anulada', [
            'nota' => $nota->numero,
            'pedido' => $nota->pedido_numero,
            'cliente' => $nota->cliente_nombre,
            'almacen' => $nota->almacen,
            'total' => (float) $nota->total,
            'motivo' => $nota->anulada_motivo,
            'por' => $nota->anulada_por,
            'cuando' => $nota->anulada_fecha?->format('d/m/Y H:i'),
            'enlace' => $nota->pedido ? route('pedidos.ver', $nota->pedido) : null,
        ], $para, $nota->pedido_numero, $usuario);
    }

    public function cobroConfirmado(Cobranza $cobranza, User $usuario): void
    {
        $cobranza->load('nota');
        $nota = $cobranza->nota;

        $this->enviar('cobro_confirmado', "Cobro confirmado · Nota {$cobranza->nota_numero} · {$cobranza->cliente_nombre}", 'cobro-confirmado',
            $this->datosCobranza($cobranza) + [
                'numero' => $cobranza->numero,
                'pagado' => (float) $nota?->total_pagado,
                'saldo' => (float) $nota?->saldo_pendiente,
                'por' => $cobranza->confirmada_por,
                'cuando' => $cobranza->fecha_confirmacion?->format('d/m/Y H:i'),
            ],
            [$this->correoVendedor($cobranza->vendedor_codigo), $cobranza->cargadoPor?->email],
            $cobranza->pedido_numero,
            $usuario,
        );
    }

    public function correccionNumero(Cobranza $cobranza, string $anterior, User $usuario): void
    {
        $this->enviar('correccion_cobranza', "Corrección de N° de cobranza · Nota {$cobranza->nota_numero} · {$cobranza->cliente_nombre}", 'correccion-cobranza',
            $this->datosCobranza($cobranza) + [
                'anterior' => $anterior,
                'nuevo' => $cobranza->numero,
                'motivo' => $cobranza->numero_motivo,
                'por' => $usuario->name,
                'cuando' => now()->format('d/m/Y H:i'),
            ],
            [$this->correoVendedor($cobranza->vendedor_codigo), $usuario->email],
            $cobranza->pedido_numero,
            $usuario,
        );
    }

    /**
     * Un correo por proforma con cambios (o por todas si cambio la cabecera).
     *
     * @param  array{lineas: array<string, array<string, list<array<string,mixed>>>>, cabecera: array<string, mixed>}  $cambios
     */
    public function proformasModificadas(Pedido $pedido, array $cambios, User $usuario): void
    {
        $pedido->load(['lineas', 'proformas', 'cliente']);
        $claves = collect(array_keys($cambios['lineas']));

        if ($cambios['cabecera'] !== []) {
            $claves = $claves->merge($pedido->proformas->pluck('clave'));
        }

        $cabecera = [];

        if (isset($cambios['cabecera']['forma_pago'])) {
            $cabecera[] = 'Forma de pago: '.$cambios['cabecera']['forma_pago'][0].' → '.$cambios['cabecera']['forma_pago'][1];
        }

        if (isset($cambios['cabecera']['dias'])) {
            [$da, $dn, $va, $vn] = $cambios['cabecera']['dias'];
            $cabecera[] = 'Días de crédito: '.($da ?? 0).' → '.($dn ?? 0).' · vence '.($va?->format('d/m/Y') ?? '—').' → '.($vn?->format('d/m/Y') ?? '—');
        }

        foreach ($claves->unique()->sort() as $clave) {
            $proforma = $pedido->proformas->firstWhere('clave', $clave);
            $lineas = $pedido->lineas->where('proforma_clave', $clave);
            $detalle = $cambios['lineas'][$clave] ?? [];
            $almacen = $proforma?->almacen ?? Almacenes::porCodigo((string) str($clave)->afterLast('-'))->nombre;

            $totalAhora = round($lineas->sum(fn (PedidoLinea $l) => $l->subtotal()), 2);
            $totalAntes = $totalAhora
                - collect($detalle['nuevos'] ?? [])->sum(fn ($n) => $n['cantidad'] * $n['precio'])
                + collect($detalle['eliminados'] ?? [])->sum(fn ($n) => $n['cantidad'] * $n['precio'])
                - collect($detalle['modificados'] ?? [])->sum(fn ($n) => $n['cantidad'] * $n['precio'] - $n['cantidad_antes'] * $n['precio_antes']);

            $modificados = collect($detalle['nuevos'] ?? [])->pluck('codigo')
                ->merge(collect($detalle['modificados'] ?? [])->pluck('codigo'))->all();

            $numero = $proforma?->numero ? 'N° '.$proforma->numero : 'sin número';

            $this->enviar('proforma_modificada', "Proforma modificada · {$almacen} · {$numero} · {$pedido->numero} · {$pedido->cliente_nombre}", 'proforma-modificada', [
                'almacen' => $almacen,
                'numero' => $proforma?->numero,
                'pedido' => $pedido->numero,
                'por' => $usuario->name,
                'cuando' => now()->format('d/m/Y H:i'),
                'cabecera' => $cabecera,
                'cambios' => $detalle,
                'total_antes' => round($totalAntes, 2),
                'total_ahora' => $totalAhora,
                'kg' => $lineas->sum(fn (PedidoLinea $l) => $l->peso()),
                'cliente' => $pedido->cliente_nombre,
                'cliente_codigo' => $pedido->cliente_codigo,
                'direccion' => $pedido->cliente?->direccionLegible(),
                'telefono' => $pedido->cliente?->telefonoLegible(),
                'vendedor' => trim($pedido->vendedor_codigo.' — '.$pedido->vendedor_nombre),
                'fecha_pedido' => $pedido->fecha->format('d/m/Y'),
                'forma_pago' => $pedido->forma_pago?->value,
                'dias' => $pedido->dias_credito,
                'vence' => $pedido->fecha_vencimiento?->format('d/m/Y'),
                'lineas' => $lineas->map(fn (PedidoLinea $l) => [
                    'codigo' => $l->producto_codigo,
                    'descripcion' => $l->producto_descripcion,
                    'cantidad' => (float) $l->cantidad,
                    'unidad' => $l->producto_unidad,
                    'precio' => (float) $l->precio_unitario,
                    'subtotal' => $l->subtotal(),
                    'resaltar' => in_array($l->producto_codigo, $modificados, true),
                ])->values()->all(),
                'total_pedido' => (float) $pedido->total,
                'enlace' => route('pedidos.ver', $pedido),
            ], [Ajuste::valor('correo_rol_almacen'), $this->correoVendedor($pedido->vendedor_codigo), $usuario->email], $pedido->numero, $usuario);
        }
    }

    // ------------------------------------------------------------ bandeja

    /** Reenvia un correo fallido desde la administracion. */
    public function reintentar(Correo $correo): void
    {
        $correo->forceFill(['estado' => 'en_cola', 'error' => null])->save();
        $this->despachar($correo);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<string|null>  $para
     */
    private function enviar(string $evento, string $asunto, string $vista, array $datos, array $para, ?string $referencia, User $usuario): void
    {
        $para = $this->limpiar($para);
        $copiaOculta = $this->limpiar(Ajuste::correos('correo_copia_externa'));

        if ($para === []) {
            return;
        }

        try {
            $aviso = (new Aviso($asunto, $vista, $datos))->to($para);

            if ($copiaOculta !== []) {
                $aviso->bcc(array_values(array_diff($copiaOculta, $para)));
            }

            $correo = Correo::query()->create([
                'evento' => $evento,
                'asunto' => mb_substr($asunto, 0, 300),
                'destinatarios' => implode('; ', $para),
                'referencia' => $referencia,
                'estado' => 'en_cola',
                'carga' => serialize($aviso),
                'user_id' => $usuario->getKey(),
            ]);

            $this->despachar($correo);

            Rastro::registrar(AccionBitacora::Correo, "Aviso «{$asunto}» a ".implode(', ', $para), 'Correo', $correo->getKey(), null, $referencia, $usuario);
        } catch (Throwable $e) {
            // Un correo jamas revierte la operacion
            report($e);
        }
    }

    private function despachar(Correo $correo): void
    {
        try {
            EnviarAviso::dispatch($correo)->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<string|null>  $lista
     * @return list<string>
     */
    private function limpiar(array $lista): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($c) => mb_strtolower(trim((string) $c)), $lista),
            fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }

    private function correoVendedor(?string $codigo): ?string
    {
        return $codigo ? User::query()->where('codigo', $codigo)->value('email') : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosCobranza(Cobranza $cobranza): array
    {
        return [
            'nota' => $cobranza->nota_numero,
            'pedido' => $cobranza->pedido_numero,
            'cliente' => $cobranza->cliente_nombre,
            'vendedor' => $cobranza->vendedor_nombre,
            'monto' => (float) $cobranza->monto,
            'forma_pago' => $cobranza->forma_pago?->value,
            'tipo_pago' => $cobranza->tipo_pago?->value,
            'saldo_antes' => (float) $cobranza->saldo_antes,
            'saldo_despues' => (float) $cobranza->saldo_despues,
            'nota_total' => (float) $cobranza->nota_total,
            'fecha' => $cobranza->fecha->format('d/m/Y H:i'),
        ];
    }

    /** Envio inmediato de prueba desde la administracion. */
    public function prueba(string $para): void
    {
        Mail::to($para)->send(new Aviso('Prueba de correo · Control de Pedidos', 'prueba', ['cuando' => now()->format('d/m/Y H:i')]));
    }
}
