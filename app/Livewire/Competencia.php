<?php

namespace App\Livewire;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Relevamiento;
use App\Servicios\GuardarRelevamiento;
use App\Support\VentaEnCurso;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/** Pantalla Competencia: precios de la competencia observados en un cliente. */
#[Title('Competencia')]
class Competencia extends Pantalla
{
    public string $buscarCliente = '';

    public ?int $cliente_id = null;

    public string $competidor = '';

    public ?string $tipo = null;

    public string $descripcion = '';

    public string $medida = '';

    public string $unidad = '';

    public $precio = null;

    public $cantidad = null;

    public string $observacion = '';

    public function mount(): void
    {
        // Si hay una visita en curso, se propone su cliente
        $this->cliente_id = VentaEnCurso::visita()?->cliente_id;
    }

    #[Computed]
    public function clientes()
    {
        return mb_strlen(trim($this->buscarCliente)) < 2
            ? collect()
            : Cliente::query()->buscar($this->buscarCliente)->orderBy('nombre')->limit(8)->get();
    }

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->cliente_id ? Cliente::query()->find($this->cliente_id) : null;
    }

    /** @return list<string> */
    #[Computed]
    public function unidades(): array
    {
        return Producto::query()->whereNotNull('unidad')->distinct()->orderBy('unidad')->pluck('unidad')
            ->map(fn ($u) => mb_strtoupper(trim($u)))->filter()->unique()->values()->all();
    }

    /** @return list<string> */
    #[Computed]
    public function competidores(): array
    {
        return Relevamiento::query()->select('competidor')->distinct()->orderBy('competidor')->limit(200)->pluck('competidor')->all();
    }

    #[Computed]
    public function hoy()
    {
        return Relevamiento::query()
            ->where(fn ($q) => $q->where('cargado_por_id', $this->usuario()->getKey())->orWhere('vendedor_id', $this->usuario()->getKey()))
            ->where('fecha', '>=', today())
            ->latest('fecha')->get();
    }

    /** Comparacion rapida: ultimos precios relevados de productos parecidos. */
    #[Computed]
    public function referencias()
    {
        if (mb_strlen(trim($this->descripcion)) < 3) {
            return collect();
        }

        return Relevamiento::query()->where('descripcion', 'like', '%'.trim($this->descripcion).'%')->latest('fecha')->limit(5)->get();
    }

    public function elegirCliente(int $id): void
    {
        $this->cliente_id = $id;
        $this->buscarCliente = '';
    }

    public function guardar(GuardarRelevamiento $servicio): void
    {
        $usuario = $this->usuario();

        $hecho = $this->intentar(fn () => $servicio->ejecutar($usuario, VentaEnCurso::vendedor($usuario), [
            'cliente_id' => $this->cliente_id,
            'competidor' => $this->competidor,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
            'medida' => $this->medida,
            'unidad' => $this->unidad,
            'precio' => $this->precio,
            'cantidad' => $this->cantidad,
            'observacion' => $this->observacion,
        ]));

        if ($hecho) {
            $this->avisar('exito', "Guardado · {$hecho->competidor} · {$hecho->descripcion}");
            // Se conservan cliente y competidor para cargar varios productos seguidos
            $this->reset(['tipo', 'descripcion', 'medida', 'unidad', 'precio', 'cantidad', 'observacion']);
            unset($this->hoy, $this->competidores);
        }
    }

    public function eliminar(int $id, GuardarRelevamiento $servicio): void
    {
        $relevamiento = Relevamiento::query()->find($id);

        if ($relevamiento) {
            $this->intentar(fn () => $servicio->eliminar($this->usuario(), $relevamiento), 'Relevamiento eliminado');
            unset($this->hoy);
        }
    }

    public function render()
    {
        return view('pantallas.competencia');
    }
}
