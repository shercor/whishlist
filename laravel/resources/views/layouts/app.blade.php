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
                <a href="{{ route('follows.index') }}" @class(['activo' => request()->routeIs('follows.*')])>Mi gente</a>
                <a href="{{ route('reservations.index') }}" @class(['activo' => request()->routeIs('reservations.*')])>Voy a regalar</a>
                <a href="{{ route('access.index') }}" @class(['activo' => request()->routeIs('access.*')])>Solicitudes</a>
            </nav>
            @include('layouts.tema')
            <form method="POST" action="{{ route('logout') }}" class="salir">
                @csrf
                {{-- Tu propio arroba a la vista: es lo que le tienes que dictar
                     a alguien para que te encuentre. --}}
                <a class="quien" href="{{ route('profile.edit') }}">
                    <x-avatar :usuario="auth()->user()" tamano="chico" />
                    <span>{{ auth()->user()->handle() }}</span>
                </a>
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

    {{-- Los avisos flotantes se apilan acá. Vive fuera de <main> para que no
         empuje nada del contenido. --}}
    <div class="brindis" id="brindis" role="status" aria-live="polite"></div>

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

        // Aviso flotante que se va solo. Flota sobre la página: no empuja nada
        // ni hace saltar lo que el usuario está mirando.
        function avisar(mensaje) {
            var caja = document.getElementById('brindis');
            if (!caja) return;

            var aviso = document.createElement('p');
            aviso.style.margin = '0';
            aviso.textContent = mensaje;
            caja.appendChild(aviso);

            setTimeout(function () { aviso.remove(); }, 2600);
        }

        // Copiar al portapapeles.
        document.querySelectorAll('[data-copiar]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var campo = document.querySelector(boton.dataset.copiar);
                if (!campo) return;

                copiar(campo).then(function (copiado) {
                    boton.classList.toggle('copiado', copiado);

                    avisar(copiado
                        ? 'Enlace copiado. Pégalo donde quieras compartirlo.'
                        : 'No se pudo copiar solo: el enlace quedó seleccionado para que lo copies.');

                    setTimeout(function () { boton.classList.remove('copiado'); }, 2500);
                });
            });
        });

        // Detalle del regalo en un diálogo. showModal() —y no el atributo
        // open— es lo que trae el foco atrapado, el Escape y el backdrop.
        document.querySelectorAll('[data-abre-detalle]').forEach(function (disparador) {
            disparador.addEventListener('click', function () {
                var modal = document.getElementById(disparador.dataset.abreDetalle);
                if (modal) modal.showModal();
            });
        });

        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.querySelectorAll('[data-cierra-modal]').forEach(function (boton) {
                boton.addEventListener('click', function () { modal.close(); });
            });

            // Clic en el fondo: el propio <dialog> ocupa toda la pantalla, así
            // que un clic «fuera» llega al dialog y no a su contenido.
            modal.addEventListener('click', function (evento) {
                if (evento.target === modal) modal.close();
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

        // Previsualización de la foto de perfil. Es la misma idea que la del
        // producto, pero acá se reemplaza el avatar actual en vez de aparecer
        // debajo: dos caras a la vez confunden sobre cuál va a quedar.
        document.querySelectorAll('[data-previsualiza-avatar]').forEach(function (input) {
            var vista = document.querySelector(input.dataset.previsualizaAvatar);
            var actual = input.dataset.oculta ? document.querySelector(input.dataset.oculta) : null;
            if (!vista) return;

            input.addEventListener('change', function () {
                var archivo = input.files && input.files[0];

                if (vista.dataset.blob) {
                    URL.revokeObjectURL(vista.src);
                    delete vista.dataset.blob;
                }

                if (!archivo) {
                    vista.hidden = true;
                    if (actual) actual.hidden = false;
                    return;
                }

                vista.src = URL.createObjectURL(archivo);
                vista.dataset.blob = '1';
                vista.hidden = false;
                if (actual) actual.hidden = true;
            });
        });

        // Buscador de personas con sugerencias mientras se escribe.
        document.querySelectorAll('[data-buscador-personas]').forEach(function (campo) {
            var menu = document.getElementById(campo.getAttribute('aria-controls'));
            if (!menu) return;

            var MINIMO = 3;
            var espera = null;
            var enCurso = null;
            var activo = -1;

            function cerrar() {
                menu.hidden = true;
                menu.innerHTML = '';
                campo.setAttribute('aria-expanded', 'false');
                activo = -1;
            }

            function marcar(indice) {
                var filas = menu.querySelectorAll('li[role="option"]');
                if (!filas.length) return;

                // Da la vuelta en los extremos, que es lo que se espera de un
                // menú: desde el último, abajo vuelve al primero.
                activo = (indice + filas.length) % filas.length;

                filas.forEach(function (fila, i) {
                    fila.classList.toggle('activa', i === activo);
                    fila.setAttribute('aria-selected', i === activo ? 'true' : 'false');
                });

                filas[activo].scrollIntoView({ block: 'nearest' });
            }

            function pintar(usuarios) {
                menu.innerHTML = '';

                if (!usuarios.length) {
                    var vacio = document.createElement('li');
                    vacio.className = 'vacio-menu';
                    vacio.textContent = 'Nadie con ese usuario.';
                    menu.appendChild(vacio);
                } else {
                    usuarios.forEach(function (persona) {
                        menu.appendChild(fila(persona));
                    });
                }

                menu.hidden = false;
                campo.setAttribute('aria-expanded', 'true');
                activo = -1;
            }

            function fila(persona) {
                var li = document.createElement('li');
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');

                var a = document.createElement('a');
                a.href = persona.url;

                // Con foto va un <img>; sin foto, el mismo círculo de
                // iniciales que dibuja el componente de Blade.
                var cara;
                if (persona.avatar) {
                    cara = document.createElement('img');
                    cara.src = persona.avatar;
                    cara.alt = '';
                    cara.loading = 'lazy';
                    cara.className = 'avatar avatar-chico';
                } else {
                    cara = document.createElement('span');
                    cara.className = 'avatar avatar-chico avatar-vacio';
                    cara.setAttribute('aria-hidden', 'true');
                    cara.style.setProperty('--tono', persona.tono);
                    cara.textContent = persona.iniciales;
                }

                var textos = document.createElement('span');
                textos.style.minWidth = '0';

                var arroba = document.createElement('span');
                arroba.className = 'arroba';
                arroba.textContent = persona.handle;

                var detalle = document.createElement('span');
                detalle.className = 'detalle';
                detalle.textContent = (persona.nombre ? persona.nombre + ' · ' : '')
                    + (persona.listas === 1 ? '1 lista pública' : persona.listas + ' listas públicas');

                textos.appendChild(arroba);
                textos.appendChild(detalle);
                a.appendChild(cara);
                a.appendChild(textos);
                li.appendChild(a);

                return li;
            }

            function buscar() {
                var termino = campo.value.trim().replace(/^@/, '');

                if (termino.length < MINIMO) {
                    cerrar();
                    return;
                }

                // Se cancela la petición anterior: al escribir rápido salen
                // varias en camino y la última en llegar no es siempre la de
                // lo último que se escribió.
                if (enCurso) enCurso.abort();
                enCurso = new AbortController();

                fetch(campo.dataset.buscadorPersonas + '?q=' + encodeURIComponent(termino), {
                    signal: enCurso.signal,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                    .then(function (datos) { pintar(datos.usuarios); })
                    .catch(function (e) {
                        // Abortar es lo normal acá y no es un error que mostrar.
                        if (e && e.name === 'AbortError') return;
                        cerrar();
                    });
            }

            campo.addEventListener('input', function () {
                clearTimeout(espera);
                // Sin esta espera se dispara una petición por tecla.
                espera = setTimeout(buscar, 250);
            });

            campo.addEventListener('keydown', function (evento) {
                if (evento.key === 'Escape') { cerrar(); return; }
                if (menu.hidden) return;

                if (evento.key === 'ArrowDown') { evento.preventDefault(); marcar(activo + 1); }
                else if (evento.key === 'ArrowUp') { evento.preventDefault(); marcar(activo - 1); }
                else if (evento.key === 'Enter' && activo >= 0) {
                    // Solo con una opción marcada: si no, que envíe el
                    // formulario como siempre.
                    evento.preventDefault();
                    menu.querySelectorAll('li[role="option"] a')[activo].click();
                }
            });

            // El clic en una sugerencia tiene que llegar antes de cerrar, y
            // blur se dispara primero: de ahí el respiro.
            campo.addEventListener('blur', function () { setTimeout(cerrar, 150); });
            campo.addEventListener('focus', function () { if (campo.value.trim().length >= MINIMO) buscar(); });
        });
    </script>
</body>
</html>
