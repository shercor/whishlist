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

    {{-- Va acá arriba, antes de que se pinte nada, y a propósito bloquea: si
         corriera al final del body se vería un destello del tema contrario en
         cada carga. Sin elección guardada no toca nada y manda el sistema. --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('tema');
                if (tema === 'claro' || tema === 'oscuro') {
                    document.documentElement.dataset.tema = tema;
                }
            } catch (e) {
                /* Navegación privada con almacenamiento bloqueado: da igual. */
            }
        })();
    </script>
</head>
<body>
    @auth
        <header class="barra">
            <a class="marca" href="{{ route('wishlists.index') }}">whishlist</a>
            <nav>
                <a href="{{ route('wishlists.index') }}" @class(['activo' => request()->routeIs('wishlists.*')])>Mis listas</a>
                <a href="{{ route('discover') }}" @class(['activo' => request()->routeIs('discover')])>Descubrir</a>
                <a href="{{ route('users.search') }}" @class(['activo' => request()->routeIs('users.*')])>Personas</a>
                <a href="{{ route('reservations.index') }}" @class(['activo' => request()->routeIs('reservations.*')])>Voy a regalar</a>
                <a href="{{ route('access.index') }}" @class(['activo' => request()->routeIs('access.*')])>Solicitudes</a>
            </nav>
            @include('layouts.tema')
            <form method="POST" action="{{ route('logout') }}" class="salir">
                @csrf
                {{-- Tu propio arroba a la vista: es lo que le tienes que dictar
                     a alguien para que te encuentre. --}}
                <a class="quien" href="{{ route('profile.edit') }}">{{ auth()->user()->handle() }}</a>
                <button type="submit" class="boton-plano">Salir</button>
            </form>
        </header>
    @else
        {{-- Login y registro no tienen barra, pero sí deben poder cambiar el tema. --}}
        @include('layouts.tema', ['suelto' => true])
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

    <script>
        // El estado vive en el atributo data-tema del <html> y en localStorage.
        // Si no hay nada elegido todavía, el punto de partida es lo que dice el
        // sistema, para que el primer clic haga lo que el usuario espera.
        document.querySelectorAll('[data-cambia-tema]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var raiz = document.documentElement;
                var esOscuro = raiz.dataset.tema
                    ? raiz.dataset.tema === 'oscuro'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;

                var elegido = esOscuro ? 'claro' : 'oscuro';
                raiz.dataset.tema = elegido;

                try {
                    localStorage.setItem('tema', elegido);
                } catch (e) {
                    /* Sin dónde guardarlo: el cambio vale para esta página. */
                }
            });
        });

        // Copiar al portapapeles con confirmación visible.
        document.querySelectorAll('[data-copiar]').forEach(function (boton) {
            var original = boton.textContent.trim();

            boton.addEventListener('click', function () {
                var campo = document.querySelector(boton.dataset.copiar);
                if (!campo) return;

                copiar(campo).then(function (copiado) {
                    boton.textContent = copiado ? boton.dataset.copiado : 'Copia tú el enlace';
                    boton.classList.toggle('copiado', copiado);

                    var aviso = document.querySelector('[data-aviso-copiado]');
                    if (aviso) {
                        aviso.textContent = copiado
                            ? 'Enlace copiado. Pégalo donde quieras compartirlo.'
                            : 'No se pudo copiar solo. El enlace quedó seleccionado.';
                    }

                    setTimeout(function () {
                        boton.textContent = original;
                        boton.classList.remove('copiado');
                        if (aviso) aviso.textContent = '';
                    }, 2500);
                });
            });
        });

        function copiar(campo) {
            // navigator.clipboard solo existe en contexto seguro: https, o
            // localhost. Probando desde el celular en la misma wifi la URL es
            // http://192.168.x.x y no está, así que el respaldo no es
            // decorativo: es el camino real en ese caso.
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(campo.value)
                    .then(function () { return true; })
                    .catch(function () { return seleccionar(campo); });
            }

            return Promise.resolve(seleccionar(campo));
        }

        function seleccionar(campo) {
            campo.removeAttribute('disabled');
            campo.select();
            campo.setSelectionRange(0, campo.value.length);

            try {
                // Obsoleto pero es lo único que funciona sin contexto seguro.
                return document.execCommand('copy');
            } catch (e) {
                // Ni eso: al menos queda seleccionado para copiarlo a mano.
                return false;
            }
        }

        // Previsualización de la foto antes de guardar.
        document.querySelectorAll('[data-previsualiza]').forEach(function (input) {
            var caja = document.querySelector(input.dataset.previsualiza);
            if (!caja) return;

            var img = caja.querySelector('img');

            input.addEventListener('change', function () {
                var archivo = input.files && input.files[0];

                // Se libera la anterior: sin esto cada foto elegida deja un
                // blob retenido en memoria hasta que se recarga la página.
                if (img.dataset.blob) {
                    URL.revokeObjectURL(img.src);
                    delete img.dataset.blob;
                }

                if (!archivo) {
                    caja.hidden = true;
                    return;
                }

                img.src = URL.createObjectURL(archivo);
                img.dataset.blob = '1';
                caja.hidden = false;
            });
        });
    </script>
</body>
</html>
