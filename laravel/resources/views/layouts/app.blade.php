<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'whishlist')</title>
    {{-- CSS plano servido desde public/: sin Vite ni npm, así clonar y levantar
         no depende de un paso de build. --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @auth
        <header class="barra">
            <a class="marca" href="{{ route('wishlists.index') }}">whishlist</a>
            <nav>
                <a href="{{ route('wishlists.index') }}" @class(['activo' => request()->routeIs('wishlists.*')])>Mis listas</a>
                <a href="{{ route('discover') }}" @class(['activo' => request()->routeIs('discover')])>Descubrir</a>
                <a href="{{ route('reservations.index') }}" @class(['activo' => request()->routeIs('reservations.*')])>Voy a regalar</a>
                <a href="{{ route('access.index') }}" @class(['activo' => request()->routeIs('access.*')])>Solicitudes</a>
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="salir">
                @csrf
                <span class="quien">{{ auth()->user()->name }}</span>
                <button type="submit" class="boton-plano">Salir</button>
            </form>
        </header>
    @endauth

    <main class="contenedor">
        @if (session('status'))
            <p class="aviso aviso-ok">{{ session('status') }}</p>
        @endif

        @if (session('error'))
            <p class="aviso aviso-error">{{ session('error') }}</p>
        @endif

        @if ($errors->any())
            <ul class="aviso aviso-error">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
