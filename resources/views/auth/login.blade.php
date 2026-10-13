<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063381">
    <title>Acceso · Control de Pedidos · Plásticos Carmen</title>
    <link rel="icon" type="image/png" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        try {
            var t = localStorage.getItem('cp-tema') || 'sistema';
            document.documentElement.dataset.tema = (t === 'oscuro' || (t === 'sistema' && matchMedia('(prefers-color-scheme: dark)').matches)) ? 'oscuro' : 'claro';
        } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh font-sans antialiased">
<div class="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
    {{-- Panel de marca --}}
    <section class="relative hidden overflow-hidden bg-marca-800 p-12 text-white lg:flex lg:flex-col">
        <div class="absolute -top-32 -right-32 size-[30rem] rounded-full bg-marca-500/30 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-rojo-600/25 blur-3xl"></div>
        <div class="relative flex items-center gap-3">
            <div class="rounded-2xl bg-white p-2.5"><img src="{{ asset('img/logo-pc.png') }}" alt="Plásticos Carmen" class="h-10"></div>
            <div>
                <div class="text-lg font-semibold">Plásticos Carmen</div>
                <div class="text-sm text-white/70">Programa de Transformación</div>
            </div>
        </div>
        <div class="relative mt-auto max-w-lg">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur">
                @if ($demo)
                    <span class="size-2 rounded-full bg-amber-400 animate-latir"></span> Modo demostración · datos de prueba
                @else
                    <span class="size-2 rounded-full bg-emerald-400 animate-latir"></span> Conectado a SharePoint de la empresa
                @endif
            </div>
            <h1 class="text-4xl leading-tight font-bold">Control de Pedidos</h1>
            <p class="mt-3 text-lg text-white/75">Visitas, pedidos, despachos y cobranzas en un solo lugar, con trazabilidad de cada paso por usuario.</p>
            <div class="mt-10 grid grid-cols-3 gap-3 text-sm">
                @foreach ([['ubicacion', 'Visita'], ['carrito', 'Pedido'], ['camion', 'Despacho'], ['documento', 'Nota de venta'], ['billete', 'Cobranza'], ['huella', 'Trazabilidad']] as [$icono, $texto])
                    <div class="flex items-center gap-2 rounded-xl bg-white/8 px-3 py-2.5 backdrop-blur" style="background: rgba(255,255,255,.07)">
                        <x-icono :nombre="$icono" clase="size-4 text-white/80" /> {{ $texto }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Acceso --}}
    <section class="flex items-center justify-center p-6 sm:p-10">
        <div class="w-full max-w-md animate-subir">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('img/logo-pc.png') }}" alt="Plásticos Carmen" class="h-11">
                <div>
                    <div class="font-bold tinta">Control de Pedidos</div>
                    <div class="text-xs suave">Plásticos Carmen</div>
                </div>
            </div>

            <h2 class="text-2xl font-bold tinta">Bienvenido</h2>
            <p class="mt-1 suave">Ingrese con su cuenta de Microsoft 365 de la empresa.</p>

            @if (session('error'))
                <div class="mt-5 flex gap-3 rounded-xl border border-rojo-100 bg-rojo-50 p-3.5 text-sm text-rojo-700">
                    <x-icono nombre="alerta" clase="size-5 shrink-0" /> <span>{{ session('error') }}</span>
                </div>
            @endif

            <a href="{{ $ssoListo ? route('auth.microsoft') : '#' }}" @class(['btn-grande btn mt-6 w-full border fondo-papel linea shadow-sm hover:shadow-md', 'pointer-events-none opacity-50' => ! $ssoListo])>
                <svg class="size-5" viewBox="0 0 23 23" aria-hidden="true"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#7fba00" d="M12 1h10v10H12z"/><path fill="#00a4ef" d="M1 12h10v10H1z"/><path fill="#ffb900" d="M12 12h10v10H12z"/></svg>
                <span>Ingresar con Microsoft 365</span>
            </a>
            @unless ($ssoListo)
                <p class="mt-2 text-xs suave">El inicio de sesión con Microsoft se habilita cuando Sistemas registre la aplicación en Entra ID.</p>
            @endunless

            @if ($demo)
                <div class="mt-8 rounded-2xl border border-dashed border-amber-300 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-amber-700">
                        <x-icono nombre="chispa" clase="size-4" /> Modo demostración
                    </div>
                    <p class="mt-1 text-xs suave">Elija un usuario para recorrer el sistema con sus permisos. Este acceso no existe en producción.</p>
                    <form method="POST" action="{{ route('login.demo') }}" class="mt-3 space-y-2">
                        @csrf
                        <div class="desplazable max-h-72 space-y-1.5 overflow-y-auto pr-1">
                            @foreach ($usuarios as $u)
                                <button name="usuario" value="{{ $u->id }}" class="flex w-full items-center gap-3 rounded-xl border p-2.5 text-left transition hover:border-marca-300 hover:shadow-sm linea fondo-papel">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-marca-50 text-xs font-bold text-marca-700">{{ $u->iniciales() }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold">{{ $u->name }}</span>
                                        <span class="block truncate text-xs suave">{{ $u->email }}</span>
                                    </span>
                                    <span class="flex flex-col items-end gap-1">
                                        <span class="pastilla tono-azul">{{ $u->rol->etiqueta() }}</span>
                                        @if ($u->carga_por_otros) <span class="pastilla tono-neutro">Supervisor</span> @endif
                                        @if ($u->despacha) <span class="pastilla tono-neutro">Despacha</span> @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </form>
                </div>
            @endif

            <p class="mt-10 text-center text-xs suave">
                <a href="{{ config('pedidos.central_url') }}" class="hover:underline">← Volver al portal central</a>
            </p>
        </div>
    </section>
</div>
</body>
</html>
