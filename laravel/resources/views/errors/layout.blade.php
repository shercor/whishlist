<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Algo no salió') · whishlist</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    {{-- El mismo guion de tema que el layout normal: sin él, aterrizar en un
         error desde una aplicación en oscuro daba un fogonazo blanco. --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('tema');
                if (tema === 'claro' || tema === 'oscuro') {
                    document.documentElement.dataset.tema = tema;
                }
            } catch (e) {}
        })();
    </script>
</head>
{{-- A propósito **sin la barra de navegación**.

     La barra le pregunta a la base cuántas solicitudes y notificaciones tienes
     sin ver. En un 500 provocado justamente por la base, pintarla volvería a
     reventar dentro del manejador de errores, y lo que ve la persona es la
     pantalla en blanco de un error dentro de otro. La salida es un enlace, que
     no necesita consultar nada. --}}
<body>
    <main class="contenedor angosto error-pagina">
        <p class="error-codigo">@yield('codigo')</p>
        <h1>@yield('titulo')</h1>
        <p class="bajada">@yield('explicacion')</p>

        <div class="acciones">
            @yield('salida')
        </div>
    </main>
</body>
</html>
