@php
    use App\Support\Navegacion;
    $usuario = auth()->user();
    $grupos = $usuario ? Navegacion::para($usuario) : [];
    $movil = $usuario ? Navegacion::movil($usuario) : [];
    $demo = config('pedidos.modo') !== 'sharepoint';
    $titulo = $title ?? 'Control de Pedidos';
@endphp
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth <meta name="usuario" content="{{ $usuario->getKey() }}"> @endauth
    <meta name="theme-color" content="#063381">
    <title>{{ $titulo }} · Pedidos · Plásticos Carmen</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('cp-tema') || 'sistema';
                var o = t === 'oscuro' || (t === 'sistema' && matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.dataset.tema = o ? 'oscuro' : 'claro';
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh font-sans antialiased" x-data="{ menu: false }">

@auth
<div class="flex min-h-dvh">
    {{-- ============ Barra lateral (escritorio) ============ --}}
    <aside class="no-imprimir fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r lg:flex fondo-papel linea">
        <a href="{{ route('inicio') }}" wire:navigate class="flex items-center gap-3 px-5 pt-5 pb-4">
            <img src="{{ asset('img/logo-pc.png') }}" alt="Plásticos Carmen" class="h-9 w-auto">
            <div class="leading-tight">
                <div class="text-sm font-bold tinta">Control de Pedidos</div>
                <div class="text-[11px] suave">Plásticos Carmen</div>
            </div>
        </a>

        <button type="button" @click="$dispatch('abrir-buscador')" class="mx-4 mb-3 flex items-center gap-2 rounded-xl border px-3 py-2 text-sm suave linea fondo-papel-2 hover:border-marca-300">
            <x-icono nombre="lupa" clase="size-4" />
            <span class="flex-1 text-left">Buscar…</span>
            <kbd class="rounded-md border px-1.5 text-[10px] font-semibold linea">Ctrl K</kbd>
        </button>

        <nav class="desplazable flex-1 space-y-5 overflow-y-auto px-3 pb-4">
            @foreach ($grupos as $grupo)
                <div>
                    <div class="rotulo px-3 pb-1.5">{{ $grupo['titulo'] }}</div>
                    <div class="space-y-0.5">
                        @foreach ($grupo['items'] as $item)
                            @php($activo = request()->routeIs($item['ruta']) || request()->routeIs($item['ruta'].'.*') || ($item['ruta'] === 'pedidos.index' && request()->routeIs('pedidos.ver')))
                            <a href="{{ route($item['ruta']) }}" wire:navigate @class(['nav-item', 'nav-activo' => $activo])>
                                <x-icono :nombre="$item['icono']" clase="size-[18px]" />
                                <span class="flex-1">{{ $item['etiqueta'] }}</span>
                                @if (! empty($item['insignia']))
                                    <span @class(['rounded-full px-2 py-0.5 text-[11px] font-bold', 'bg-white/20 text-white' => $activo, 'bg-rojo-600 text-white' => ! $activo])>{{ $item['insignia'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t p-3 linea">
            <a href="{{ config('pedidos.central_url') }}" class="nav-item" data-rastro="Volvió al portal central" data-rastro-accion="abrir">
                <x-icono nombre="portal" clase="size-[18px]" /> Portal central
            </a>
        </div>
    </aside>

    {{-- ============ Contenido ============ --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="no-imprimir sticky top-0 z-30 border-b backdrop-blur-xl linea" style="background: color-mix(in srgb, var(--papel) 82%, transparent)">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                <a href="{{ route('inicio') }}" wire:navigate class="lg:hidden">
                    <img src="{{ asset('img/logo-pc.png') }}" alt="Plásticos Carmen" class="h-8 w-auto">
                </a>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-semibold tinta sm:text-lg">{{ $titulo }}</h1>
                </div>

                @if ($demo)
                    <span class="pastilla tono-ambar hidden sm:inline-flex" title="Datos locales de demostración: nada se escribe en SharePoint">
                        <x-icono nombre="chispa" clase="size-3.5" /> Modo demostración
                    </span>
                @endif

                <button type="button" @click="$dispatch('abrir-buscador')" class="btn-fantasma btn !p-2 lg:hidden" aria-label="Buscar">
                    <x-icono nombre="lupa" />
                </button>

                {{-- Tema --}}
                <div x-data="{ tema: window.temaActual ? window.temaActual() : 'sistema' }" class="relative">
                    <button type="button" class="btn-fantasma btn !p-2" aria-label="Cambiar tema"
                        @click="tema = tema === 'claro' ? 'oscuro' : (tema === 'oscuro' ? 'sistema' : 'claro'); window.cambiarTema(tema)"
                        :title="'Tema: ' + tema">
                        <x-icono nombre="sol" x-show="tema === 'claro'" />
                        <x-icono nombre="luna" x-show="tema === 'oscuro'" x-cloak />
                        <x-icono nombre="monitor" x-show="tema === 'sistema'" x-cloak />
                    </button>
                </div>

                {{-- Usuario --}}
                <div x-data="{ abierto: false }" class="relative">
                    <button type="button" @click="abierto = !abierto" @click.outside="abierto = false" class="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-[var(--papel-2)]">
                        <span class="grid size-9 place-items-center rounded-full bg-gradient-to-br from-marca-500 to-marca-700 text-xs font-bold text-white">{{ $usuario->iniciales() }}</span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-sm font-semibold">{{ $usuario->nombreCorto() }}</span>
                            <span class="block text-[11px] suave">{{ $usuario->rol?->etiqueta() }}{{ $usuario->codigo ? ' · '.$usuario->codigo : '' }}</span>
                        </span>
                        <x-icono nombre="chevron-abajo" clase="size-4 suave hidden md:block" />
                    </button>
                    <div x-show="abierto" x-transition.origin.top.right x-cloak class="tarjeta absolute right-0 mt-2 w-64 p-2 animate-entrar">
                        <div class="px-3 py-2">
                            <div class="text-sm font-semibold">{{ $usuario->name }}</div>
                            <div class="text-xs suave truncate">{{ $usuario->email }}</div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span class="pastilla tono-azul">{{ $usuario->rol?->etiqueta() }}</span>
                                @if ($usuario->puedeElegirVendedor()) <span class="pastilla tono-neutro">Supervisor</span> @endif
                                @if ($usuario->vendedorDespacha()) <span class="pastilla tono-neutro">Despacha</span> @endif
                            </div>
                        </div>
                        <div class="my-1 border-t linea"></div>
                        <a href="{{ config('pedidos.central_url') }}" class="nav-item" data-rastro="Volvió al portal central" data-rastro-accion="abrir"><x-icono nombre="portal" clase="size-4" /> Portal central</a>
                        <form method="POST" action="{{ route('salir') }}">
                            @csrf
                            <button class="nav-item w-full text-rojo-600"><x-icono nombre="salir" clase="size-4" /> Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 pt-5 pb-28 sm:px-6 lg:pb-10">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- ============ Barra inferior (celular) ============ --}}
<nav class="no-imprimir fixed inset-x-0 bottom-0 z-40 border-t pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden linea" style="background: color-mix(in srgb, var(--papel) 90%, transparent)">
    <div class="mx-auto grid max-w-lg" style="grid-template-columns: repeat({{ max(1, count($movil)) + 1 }}, minmax(0, 1fr))">
        @foreach ($movil as $item)
            @php($activo = request()->routeIs($item['ruta']) || request()->routeIs($item['ruta'].'.*'))
            <a href="{{ route($item['ruta']) }}" wire:navigate @class(['relative flex flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-medium', 'text-marca-600 dark:text-marca-200' => $activo, 'suave' => ! $activo])>
                <x-icono :nombre="$item['icono']" clase="size-[22px]" :trazo="$activo ? 2.2 : 1.8" />
                {{ $item['etiqueta'] }}
                @if (! empty($item['insignia']))
                    <span class="absolute top-1.5 left-1/2 ml-2 min-w-4 rounded-full bg-rojo-600 px-1 text-center text-[10px] font-bold text-white">{{ $item['insignia'] }}</span>
                @endif
            </a>
        @endforeach
        <button type="button" @click="menu = true" class="flex flex-col items-center gap-0.5 py-2.5 text-[10.5px] font-medium suave">
            <x-icono nombre="menu" clase="size-[22px]" /> Más
        </button>
    </div>
</nav>

{{-- Hoja «Más» en el celular --}}
<div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="menu = false">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" @click="menu = false" x-transition.opacity></div>
    <div class="absolute inset-x-0 bottom-0 max-h-[80dvh] overflow-y-auto rounded-t-3xl p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] fondo-papel animate-subir">
        <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300"></div>
        @foreach ($grupos as $grupo)
            <div class="rotulo px-2 pt-3 pb-1">{{ $grupo['titulo'] }}</div>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($grupo['items'] as $item)
                    <a href="{{ route($item['ruta']) }}" wire:navigate @click="menu = false" class="tarjeta-sec flex flex-col items-center gap-1.5 p-3 text-center text-xs font-medium">
                        <x-icono :nombre="$item['icono']" clase="size-6 text-marca-600 dark:text-marca-200" />
                        {{ $item['etiqueta'] }}
                    </a>
                @endforeach
            </div>
        @endforeach
        <form method="POST" action="{{ route('salir') }}" class="mt-4">
            @csrf
            <button class="btn-borde btn w-full text-rojo-600"><x-icono nombre="salir" clase="size-4" /> Cerrar sesión</button>
        </form>
    </div>
</div>

<livewire:buscador />
@endauth

@guest
    {{ $slot }}
@endguest

{{-- ============ Avisos (toasts) ============ --}}
<div class="no-imprimir pointer-events-none fixed inset-x-0 top-3 z-[60] flex flex-col items-center gap-2 px-3 sm:top-auto sm:right-5 sm:bottom-5 sm:left-auto sm:items-end" x-data>
    <template x-for="a in $store.avisos.lista" :key="a.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border p-3.5 pr-2 shadow-xl animate-entrar fondo-papel linea"
             :class="{ 'border-l-4 !border-l-emerald-500': a.tipo === 'exito', 'border-l-4 !border-l-rojo-600': a.tipo === 'error', 'border-l-4 !border-l-amber-500': a.tipo === 'alerta', 'border-l-4 !border-l-marca-500': a.tipo === 'info' }">
            <div class="mt-0.5 shrink-0"
                 :class="{ 'text-emerald-600': a.tipo === 'exito', 'text-rojo-600': a.tipo === 'error', 'text-amber-600': a.tipo === 'alerta', 'text-marca-500': a.tipo === 'info' }">
                <x-icono nombre="check-circulo" x-show="a.tipo === 'exito'" />
                <x-icono nombre="x-circulo" x-show="a.tipo === 'error'" />
                <x-icono nombre="alerta" x-show="a.tipo === 'alerta'" />
                <x-icono nombre="info" x-show="a.tipo === 'info'" />
            </div>
            <p class="flex-1 text-sm leading-snug" x-text="a.mensaje"></p>
            <button type="button" class="btn-fantasma btn !p-1" @click="$store.avisos.quitar(a.id)"><x-icono nombre="x" clase="size-4" /></button>
        </div>
    </template>
</div>

@if (session('aviso'))
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.Alpine.store('avisos').agregar(@js(session('aviso.tipo', 'info')), @js(session('aviso.mensaje', '')));
        });
    </script>
@endif

@livewireScripts
</body>
</html>
