<?php

namespace App\Livewire;

use App\Models\Bitacora;
use App\Models\Correo;
use App\Models\NotaVenta;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Proforma;
use App\Servicios\AnularNota;
use App\Servicios\Comprobantes;
use App\Servicios\CrearNotaVenta;
use App\Servicios\EditarPedido;
use App\Servicios\EstadoPedido;
use App\Servicios\NumerarProforma;
use App\Support\Almacenes;
use App\SharePoint\Espejo;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

/**
 * Detalle de un pedido: proformas por almacen, despacho con notas de venta,
 * anulaciones, edicion y la linea de tiempo de todo lo que paso (quien, que y cuando).
 */
class PedidoDetalle extends Pantalla
{
    use WithFileUploads;

    public Pedido $pedido;

    /** @var array<int, string> proforma_id => numero en edicion */
    public array $numeros = [];

    // ---- despacho ----
    public bool $verDespacho = false;

    public ?int $proformaDespacho = null;

    /** @var array<int, mixed> pedido_linea_id => cantidad */
    public array $cantidades = [];

    public string $notaNumero = '';

    public $comprobante = null;

    // ---- anulacion ----
    public bool $verAnular = false;

    public ?int $notaAnular = null;

    public string $motivo = '';

    // ---- edicion ----
    public bool $verEditar = false;

    public ?string $editFormaPago = null;

    public ?int $editDias = null;

    /** @var list<array{id:?int, producto_id:?int, descripcion:string, unidad:?string, almacen:string, clave:string, cantidad:mixed, precio:mixed, bloqueada:bool}> */
    public array $editLineas = [];

    public string $editBuscar = '';

    public function mount(Pedido $pedido): void
    {
        abort_unless($this->usuario()->puedeVerPedido($pedido), 403, 'Este pedido no es suyo.');

        $this->pedido = $pedido;
        $this->cargarNumeros();
    }

    private function cargarNumeros(): void
    {
        $this->numeros = $this->pedido->proformas()->pluck('numero', 'id')->map(fn ($n) => (string) $n)->all();
    }

    public function getTitle(): string
    {
        return 'Pedido '.$this->pedido->numero;
    }

    #[Computed]
    public function proformas()
    {
        return $this->pedido->proformas()->with(['lineas', 'notas' => fn ($q) => $q->with('lineas')->withCount(['cobranzas as cobros_con_pago' => fn ($c) => $c->where('hubo_pago', true)])])->get();
    }

    /** @return \Illuminate\Support\Collection<int, float> */
    #[Computed]
    public function despachado()
    {
        return EstadoPedido::despachadoPorLinea($this->pedido);
    }

    #[Computed]
    public function lineas()
    {
        return $this->pedido->lineas()->get();
    }

    /** Linea de tiempo: bitacora del documento + correos enviados. */
    #[Computed]
    public function historial()
    {
        $asientos = Bitacora::query()->where('referencia', $this->pedido->numero)->with('usuario')->latest('created_at')->limit(200)->get();
        $correos = Correo::query()->where('referencia', $this->pedido->numero)->latest()->get()->keyBy('id');

        return $asientos->map(function (Bitacora $b) use ($correos) {
            $b->setAttribute('correo', $b->entidad === 'Correo' ? $correos->get($b->entidad_id) : null);

            return $b;
        });
    }

    #[Computed]
    public function resultadosEdicion()
    {
        return mb_strlen(trim($this->editBuscar)) < 2 ? collect() : Producto::query()->buscar($this->editBuscar)->limit(8)->get();
    }

    private function refrescar(): void
    {
        $this->pedido->refresh();
        unset($this->proformas, $this->despachado, $this->lineas, $this->historial);
        $this->cargarNumeros();
    }

    // ----------------------------------------------------------- proformas

    public function numerar(int $proformaId, NumerarProforma $servicio): void
    {
        $proforma = $this->pedido->proformas()->findOrFail($proformaId);

        $hecho = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $proforma, $this->numeros[$proformaId] ?? ''));

        if ($hecho) {
            $this->avisar('exito', "Proforma {$hecho->numero} guardada");
            $this->refrescar();
        }
    }

    // ------------------------------------------------------------ despacho

    public function abrirDespacho(int $proformaId): void
    {
        $proforma = $this->pedido->proformas()->findOrFail($proformaId);

        if (! $proforma->tieneNumero()) {
            $this->avisar('alerta', 'Cargue primero el número de esta proforma para poder despachar.');

            return;
        }

        // Antes de despachar se trae lo ultimo de SharePoint (por si otro despacho llego de afuera)
        app(Espejo::class)->refrescar($proforma);

        $this->proformaDespacho = $proformaId;
        $this->cantidades = $proforma->lineas()->pluck('id')->mapWithKeys(fn ($id) => [$id => null])->all();
        $this->reset(['notaNumero', 'comprobante']);
        $this->resetErrorBag();
        $this->verDespacho = true;
    }

    public function todoPendiente(): void
    {
        foreach (array_keys($this->cantidades) as $lineaId) {
            $linea = $this->lineas->firstWhere('id', $lineaId);
            $this->cantidades[$lineaId] = $linea ? max(0, (float) $linea->cantidad - ($this->despachado[$lineaId] ?? 0)) : 0;
        }
    }

    public function despachar(CrearNotaVenta $servicio): void
    {
        if ($this->comprobante) {
            $this->validate(['comprobante' => Comprobantes::reglas()], [
                'comprobante.max' => 'El comprobante no puede pasar de 50 MB.',
                'comprobante.mimes' => 'El comprobante tiene que ser PDF o imagen.',
            ]);
        }

        $proforma = Proforma::query()->whereKey($this->proformaDespacho)->where('pedido_id', $this->pedido->getKey())->first();

        $nota = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $proforma, $this->notaNumero, $this->cantidades, $this->comprobante));

        if ($nota) {
            $this->avisar('exito', "Nota de venta {$nota->numero} creada");
            $this->verDespacho = false;
            $this->reset(['proformaDespacho', 'cantidades', 'notaNumero', 'comprobante']);
            $this->refrescar();
        }
    }

    // ----------------------------------------------------------- anulacion

    public function abrirAnular(int $notaId): void
    {
        $nota = NotaVenta::query()->where('pedido_id', $this->pedido->getKey())->findOrFail($notaId);

        if ($nota->anulada) {
            $this->avisar('alerta', 'Esta nota ya está anulada');

            return;
        }

        if ($nota->cobranzas()->where('hubo_pago', true)->exists()) {
            $this->avisar('error', 'Esta nota tiene cobranzas registradas. No se puede anular.');

            return;
        }

        $this->notaAnular = $notaId;
        $this->motivo = '';
        $this->resetErrorBag();
        $this->verAnular = true;
    }

    public function anular(AnularNota $servicio): void
    {
        $nota = NotaVenta::query()->where('pedido_id', $this->pedido->getKey())->find($this->notaAnular);

        $hecho = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $nota, $this->motivo));

        if ($hecho) {
            $this->avisar('exito', "Nota {$hecho->numero} anulada");
            $this->verAnular = false;
            $this->refrescar();
        }
    }

    // ------------------------------------------------------------- edicion

    public function abrirEdicion(): void
    {
        abort_unless($this->usuario()->puedeEditar($this->pedido), 403);

        $bloqueadas = $this->proformas->filter(fn (Proforma $p) => $p->notas->contains(fn ($n) => ! $n->anulada))->pluck('clave')->all();

        $this->editFormaPago = $this->pedido->forma_pago?->value;
        $this->editDias = $this->pedido->dias_credito;
        $this->editLineas = $this->lineas->map(fn ($l) => [
            'id' => $l->id,
            'producto_id' => $l->producto_id ?? Producto::query()->where('codigo', $l->producto_codigo)->value('id'),
            'descripcion' => $l->producto_descripcion,
            'unidad' => $l->producto_unidad,
            'almacen' => $l->almacen,
            'clave' => $l->proforma_clave,
            'cantidad' => (float) $l->cantidad,
            'precio' => (float) $l->precio_unitario,
            'bloqueada' => in_array($l->proforma_clave, $bloqueadas, true),
        ])->values()->all();
        $this->editBuscar = '';
        $this->resetErrorBag();
        $this->verEditar = true;
    }

    public function agregarEdicion(int $productoId): void
    {
        $producto = Producto::query()->findOrFail($productoId);

        if (collect($this->editLineas)->contains(fn ($l) => $l['producto_id'] === $productoId)) {
            $this->avisar('error', 'Ese producto ya está en el pedido: cambie la cantidad en su línea');

            return;
        }

        $almacen = Almacenes::paraProducto($producto->codigo);
        $clave = $this->pedido->numero.'-'.$almacen->codigo;

        if (collect($this->editLineas)->contains(fn ($l) => $l['clave'] === $clave && $l['bloqueada'])) {
            $this->avisar('error', "El {$almacen->nombre} ya tiene notas de venta: no se le pueden agregar productos");

            return;
        }

        $this->editLineas[] = [
            'id' => null, 'producto_id' => $producto->id, 'descripcion' => $producto->descripcion,
            'unidad' => $producto->unidadNormalizada(), 'almacen' => $almacen->nombre, 'clave' => $clave,
            'cantidad' => null, 'precio' => null, 'bloqueada' => false,
        ];
        $this->editBuscar = '';
    }

    public function quitarEdicion(int $indice): void
    {
        if (! ($this->editLineas[$indice]['bloqueada'] ?? true)) {
            unset($this->editLineas[$indice]);
            $this->editLineas = array_values($this->editLineas);
        }
    }

    public function guardarEdicion(EditarPedido $servicio): void
    {
        $hecho = $this->intentar(fn () => $servicio->ejecutar(
            $this->usuario(),
            $this->pedido,
            ['forma_pago' => $this->editFormaPago, 'dias_credito' => $this->editDias],
            array_map(fn ($l) => ['id' => $l['id'], 'producto_id' => $l['producto_id'], 'cantidad' => $l['cantidad'], 'precio' => $l['precio']], $this->editLineas),
        ));

        if ($hecho) {
            $this->avisar('exito', "Pedido {$this->pedido->numero} actualizado");
            $this->verEditar = false;
            $this->refrescar();
        }
    }

    public function render()
    {
        return view('pantallas.pedido-detalle', [
            'usuario' => $this->usuario(),
            'puedeDespachar' => $this->usuario()->puedeDespachar($this->pedido),
            'puedeEditar' => $this->usuario()->puedeEditar($this->pedido),
        ])->title($this->getTitle());
    }
}
