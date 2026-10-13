<?php

namespace App\Livewire;

use App\Models\Almacen;
use App\Models\Pedido;
use App\Models\PedidoLinea;
use App\Models\Producto;
use App\Models\Visita;
use App\Servicios\GuardarPedido;
use App\Support\Almacenes;
use App\Support\Formato;
use App\Support\VentaEnCurso;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Pantalla Pedido: armar el carrito sobre la visita registrada y guardarlo.
 * El carrito vive en la sesion: no se pierde al cambiar de pantalla.
 */
#[Title('Pedido')]
class Carrito extends Pantalla
{
    public string $buscar = '';

    public ?int $producto_id = null;

    public $cantidad = null;

    public $precio = null;

    #[Computed]
    public function visita(): ?Visita
    {
        return VentaEnCurso::visita();
    }

    #[Computed]
    public function resultados()
    {
        if (mb_strlen(trim($this->buscar)) < 2) {
            return collect();
        }

        return Producto::query()->buscar($this->buscar)->orderBy('descripcion')->limit(10)->get();
    }

    #[Computed]
    public function producto(): ?Producto
    {
        return $this->producto_id ? Producto::query()->find($this->producto_id) : null;
    }

    /** Ultimo precio al que se le vendio este producto a este cliente. */
    #[Computed]
    public function ultimoPrecio(): ?float
    {
        if (! $this->producto || ! $this->visita?->cliente_id) {
            return null;
        }

        $precio = PedidoLinea::query()
            ->where('producto_codigo', $this->producto->codigo)
            ->whereHas('pedido', fn ($q) => $q->where('cliente_id', $this->visita->cliente_id))
            ->latest('id')->value('precio_unitario');

        return $precio !== null ? (float) $precio : null;
    }

    #[Computed]
    public function ultimoPedido(): ?Pedido
    {
        if (! $this->visita?->cliente_id) {
            return null;
        }

        return Pedido::query()->where('cliente_id', $this->visita->cliente_id)->latest('fecha')->with('lineas')->first();
    }

    /**
     * Carrito agrupado por almacen (asi se ve como van a quedar las proformas).
     *
     * @return array<string, array{almacen:Almacen, items:list<array<string,mixed>>, total:float, kg:float}>
     */
    #[Computed]
    public function grupos(): array
    {
        $grupos = [];

        foreach (VentaEnCurso::carritoDetallado() as $indice => $item) {
            $almacen = $item['producto']->almacen();
            $grupos[$almacen->codigo] ??= ['almacen' => $almacen, 'items' => [], 'total' => 0.0, 'kg' => 0.0];
            $grupos[$almacen->codigo]['items'][] = $item + ['indice' => $indice];
            $grupos[$almacen->codigo]['total'] += $item['cantidad'] * $item['precio'];
            $grupos[$almacen->codigo]['kg'] += $item['cantidad'] * (float) $item['producto']->peso_promedio;
        }

        ksort($grupos);

        return $grupos;
    }

    public function elegir(int $id): void
    {
        $this->producto_id = $id;
        $this->buscar = '';
        unset($this->producto, $this->ultimoPrecio);
        $this->precio = $this->ultimoPrecio;
        $this->cantidad = null;
        $this->dispatch('enfocar-cantidad');
    }

    public function agregar(): void
    {
        $this->resetErrorBag();
        $producto = $this->producto;
        $cantidad = (float) $this->cantidad;
        $precio = (float) $this->precio;

        $error = match (true) {
            ! $this->visita => 'Primero registre la visita',
            ! $producto => 'Falta elegir el producto',
            $cantidad < 1 => 'La cantidad tiene que ser 1 o más',
            $producto->esPorPieza() && floor($cantidad) != $cantidad => 'Las piezas se venden enteras: la cantidad no puede tener decimales',
            $precio <= 0 => 'El precio unitario tiene que ser mayor a cero',
            default => null,
        };

        if ($error) {
            $this->addError(match ($error) {
                'Falta elegir el producto' => 'producto_id',
                'El precio unitario tiene que ser mayor a cero' => 'precio',
                default => 'cantidad',
            }, $error);
            $this->avisar('error', $error);

            return;
        }

        $carrito = VentaEnCurso::carrito();
        $existente = collect($carrito)->search(fn ($i) => $i['producto_id'] === $producto->id);

        // El mismo producto dos veces rompia el calculo de pendientes: se suma a su linea
        if ($existente !== false) {
            $carrito[$existente]['cantidad'] += $cantidad;
            $carrito[$existente]['precio'] = $precio;
            $this->avisar('info', "Se sumó a la línea existente de {$producto->descripcion}");
        } else {
            $carrito[] = ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio' => $precio];
        }

        VentaEnCurso::fijarCarrito($carrito);
        VentaEnCurso::olvidarGuardado();
        $this->reset(['producto_id', 'cantidad', 'precio']);
        unset($this->grupos);
        $this->dispatch('enfocar-busqueda');
    }

    public function actualizar(int $indice, string $campo, $valor): void
    {
        $carrito = VentaEnCurso::carrito();

        if (! isset($carrito[$indice]) || ! in_array($campo, ['cantidad', 'precio'], true)) {
            return;
        }

        $carrito[$indice][$campo] = max(0, (float) $valor);
        VentaEnCurso::fijarCarrito($carrito);
        unset($this->grupos);
    }

    public function quitar(int $indice): void
    {
        $carrito = VentaEnCurso::carrito();
        unset($carrito[$indice]);
        VentaEnCurso::fijarCarrito(array_values($carrito));
        unset($this->grupos);
    }

    /** Copia los productos y precios del ultimo pedido del cliente. */
    public function repetirUltimo(): void
    {
        $ultimo = $this->ultimoPedido;

        if (! $ultimo) {
            return;
        }

        $carrito = collect(VentaEnCurso::carrito())->keyBy('producto_id');

        foreach ($ultimo->lineas as $linea) {
            $id = $linea->producto_id ?? Producto::query()->where('codigo', $linea->producto_codigo)->value('id');

            if ($id && ! $carrito->has($id)) {
                $carrito[$id] = ['producto_id' => $id, 'cantidad' => (float) $linea->cantidad, 'precio' => (float) $linea->precio_unitario];
            }
        }

        VentaEnCurso::fijarCarrito($carrito->values()->all());
        unset($this->grupos);
        $this->avisar('exito', 'Se copiaron los productos del último pedido. Revise cantidades y precios.');
    }

    public function vaciar(): void
    {
        VentaEnCurso::fijarCarrito([]);
        unset($this->grupos);
    }

    public function guardar(GuardarPedido $servicio): void
    {
        $pedido = $this->intentar(fn () => $servicio->ejecutar($this->usuario(), $this->visita?->load('vendedor'), VentaEnCurso::carrito()));

        if ($pedido) {
            $almacenes = $pedido->proformas()->count();
            VentaEnCurso::marcarGuardado($pedido->numero, $pedido->lineas()->count(), (float) $pedido->total);
            unset($this->grupos, $this->ultimoPedido);
            $this->avisar('exito', 'Pedido guardado: '.$pedido->lineas()->count().' productos · '.$almacenes.' almacén(es) · '.Formato::bs($pedido->total));
        }
    }

    public function otroPedido(): void
    {
        VentaEnCurso::olvidarGuardado();
    }

    public function render()
    {
        $usuario = $this->usuario();

        return view('pantallas.carrito', [
            'guardado' => VentaEnCurso::guardado(),
            'atribuido' => VentaEnCurso::vendedor($usuario),
            'usuario' => $usuario,
            'total' => collect($this->grupos)->sum('total'),
            'kg' => collect($this->grupos)->sum('kg'),
            'lineas' => collect($this->grupos)->sum(fn ($g) => count($g['items'])),
            'almacenDe' => fn (Producto $p) => Almacenes::paraProducto($p->codigo),
        ]);
    }
}
