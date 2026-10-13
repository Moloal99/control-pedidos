<?php

namespace App\Livewire;

use App\Enums\EstadoCobranza;
use App\Models\Ajuste;
use App\Models\Cobranza;
use App\Servicios\ConfirmarCobranza;
use App\Servicios\CorregirNumeroCobranza;
use App\Support\Formato;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Cobranzas: revisar el comprobante, confirmar con el N° del ERP y corregirlo si hace falta. */
#[Title('Cobranzas por confirmar')]
class Cobranzas extends Pantalla
{
    use WithPagination;

    #[Url(as: 'ver', except: 'pendientes')]
    public string $filtro = 'pendientes';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(as: 'cobranza', except: null)]
    public ?int $seleccion = null;

    public string $numero = '';

    public bool $verCorregir = false;

    public string $numeroNuevo = '';

    public string $motivo = '';

    public function updating($propiedad): void
    {
        if (in_array($propiedad, ['filtro', 'buscar', 'desde', 'hasta'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function cobranza(): ?Cobranza
    {
        return $this->seleccion ? Cobranza::query()->visiblesPara($this->usuario())->with(['nota', 'cargadoPor'])->find($this->seleccion) : null;
    }

    /** @return array{pendientes:int, monto:float, hoy:float} */
    #[Computed]
    public function resumen(): array
    {
        $pendientes = Cobranza::query()->where('hubo_pago', true)->where('estado', EstadoCobranza::Pendiente->value);

        return [
            'pendientes' => (clone $pendientes)->count(),
            'monto' => (float) (clone $pendientes)->sum('monto'),
            'hoy' => (float) Cobranza::query()->where('estado', EstadoCobranza::Confirmada->value)->where('fecha_confirmacion', '>=', today())->sum('monto'),
        ];
    }

    public function elegir(int $id): void
    {
        $this->seleccion = $id;
        $this->numero = '';
        $this->resetErrorBag();
        unset($this->cobranza);
    }

    public function confirmar(ConfirmarCobranza $servicio): void
    {
        $hecho = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $this->cobranza, $this->numero));

        if ($hecho) {
            $nota = $hecho->nota;
            $this->avisar('exito', "Cobro {$hecho->numero} confirmado. Nota {$hecho->nota_numero}: pagado ".Formato::bs($nota?->total_pagado).', saldo '.Formato::bs($nota?->saldo_pendiente));
            $this->numero = '';
            unset($this->cobranza, $this->resumen);
        }
    }

    public function abrirCorregir(): void
    {
        $this->numeroNuevo = '';
        $this->motivo = '';
        $this->resetErrorBag();
        $this->verCorregir = true;
    }

    public function corregir(CorregirNumeroCobranza $servicio): void
    {
        $anterior = $this->cobranza?->numero;
        $hecho = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $this->cobranza, $this->numeroNuevo, $this->motivo));

        if ($hecho) {
            $this->avisar('exito', "Número corregido: {$anterior} → {$hecho->numero}");
            $this->verCorregir = false;
            unset($this->cobranza);
        }
    }

    public function render()
    {
        $dias = (int) Ajuste::valor('ventana_dias', 90);

        $cobranzas = Cobranza::query()
            ->visiblesPara($this->usuario())
            ->where('hubo_pago', true)
            ->where('fecha', '>=', $this->desde !== '' ? $this->desde : now()->subDays($dias)->startOfDay())
            ->when($this->hasta !== '', fn ($q) => $q->where('fecha', '<=', $this->hasta.' 23:59:59'))
            ->when($this->filtro === 'pendientes', fn ($q) => $q->where('estado', EstadoCobranza::Pendiente->value))
            ->when($this->filtro === 'confirmadas', fn ($q) => $q->where('estado', EstadoCobranza::Confirmada->value))
            ->buscar($this->buscar)
            ->latest('fecha')
            ->paginate(25);

        return view('pantallas.cobranzas', ['cobranzas' => $cobranzas, 'usuario' => $this->usuario()]);
    }
}
