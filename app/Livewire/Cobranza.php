<?php

namespace App\Livewire;

use App\Models\Cobranza as CobranzaModelo;
use App\Models\NotaVenta;
use App\Servicios\Comprobantes;
use App\Servicios\RegistrarCobranza;
use App\Support\Formato;
use App\Support\Texto;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

/**
 * Pantalla Cobranza: elegir una nota con saldo y registrar el cobro (o la
 * gestion sin pago). El saldo de la nota cambia recien cuando Cobranzas confirma.
 */
#[Title('Cobranza')]
class Cobranza extends Pantalla
{
    use WithFileUploads;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'nota', except: null)]
    public ?int $nota_id = null;

    public bool $soloVencidas = false;

    public ?bool $hubo_pago = null;

    public ?string $tipo_pago = null;

    public $monto = null;

    public ?string $forma_pago = null;

    public ?string $fecha_proximo_pago = null;

    public $comprobante = null;

    #[Computed]
    public function notas()
    {
        return NotaVenta::query()
            ->cobrables($this->usuario())
            ->when(trim($this->buscar) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('numero', Texto::like(), '%'.trim($this->buscar).'%')
                ->orWhere('cliente_nombre', Texto::like(), '%'.trim($this->buscar).'%')
                ->orWhere('vendedor_nombre', Texto::like(), '%'.trim($this->buscar).'%')))
            ->when($this->soloVencidas, fn ($q) => $q->whereNotNull('fecha_vencimiento')->where('fecha_vencimiento', '<', now()))
            ->withSum(['cobranzas as por_confirmar' => fn ($q) => $q->where('hubo_pago', true)->where('estado', 'Pendiente de confirmación')], 'monto')
            ->orderByRaw('CASE WHEN fecha_vencimiento < ? THEN 0 ELSE 1 END', [now()])
            ->latest('fecha')
            ->limit(80)
            ->get();
    }

    #[Computed]
    public function nota(): ?NotaVenta
    {
        return $this->nota_id ? NotaVenta::query()->cobrables($this->usuario())->with('lineas')->find($this->nota_id) : null;
    }

    #[Computed]
    public function disponible(): float
    {
        return $this->nota?->disponibleParaCobrar() ?? 0.0;
    }

    /** Lo ultimo que cargo este usuario: para ver que quedo «esperando aprobacion». */
    #[Computed]
    public function recientes()
    {
        return CobranzaModelo::query()->where('cargado_por_id', $this->usuario()->getKey())->latest('fecha')->limit(6)->get();
    }

    public function elegir(int $id): void
    {
        $this->nota_id = $id;
        $this->reset(['hubo_pago', 'tipo_pago', 'monto', 'forma_pago', 'fecha_proximo_pago', 'comprobante']);
        $this->resetErrorBag();
        unset($this->nota, $this->disponible);
    }

    public function cerrar(): void
    {
        $this->nota_id = null;
    }

    public function updatedHuboPago(): void
    {
        $this->reset(['tipo_pago', 'monto', 'forma_pago', 'comprobante']);
    }

    public function updatedComprobante(): void
    {
        $this->resetErrorBag('comprobante');
    }

    public function updatedTipoPago(?string $valor): void
    {
        $this->monto = $valor === 'Completo' ? $this->disponible : null;
    }

    public function guardar(RegistrarCobranza $servicio): void
    {
        if ($this->comprobante) {
            $this->validate(['comprobante' => Comprobantes::reglas()], [
                'comprobante.max' => 'El comprobante no puede pasar de 50 MB.',
                'comprobante.mimes' => 'El comprobante tiene que ser PDF o imagen (JPG, PNG, WEBP, HEIC).',
            ]);
        }

        $cobranza = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $this->nota, [
            'hubo_pago' => $this->hubo_pago,
            'tipo_pago' => $this->tipo_pago,
            'monto' => $this->monto,
            'forma_pago' => $this->forma_pago,
            'fecha_proximo_pago' => $this->fecha_proximo_pago,
        ], $this->comprobante));

        if ($cobranza) {
            $this->avisar('exito', $cobranza->hubo_pago
                ? 'Cobranza registrada por '.Formato::bs($cobranza->monto).'. Queda pendiente de confirmación.'
                : 'Gestión sin pago registrada.');
            $this->reset(['nota_id', 'hubo_pago', 'tipo_pago', 'monto', 'forma_pago', 'fecha_proximo_pago', 'comprobante']);
            unset($this->notas, $this->recientes, $this->nota);
        }
    }

    public function render()
    {
        return view('pantallas.cobranza', ['usuario' => $this->usuario()]);
    }
}
