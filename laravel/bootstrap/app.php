<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // La API vive aparte y versionada: sus rutas ya nacen bajo /api, y el
        // prefijo de versión lo pone routes/api.php.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A propósito **sin** `statefulApi()`. Ese middleware habilita sesión
        // por cookies para las peticiones que parecen venir del propio
        // frontend, que es lo que necesita una SPA servida desde el mismo
        // dominio. Acá no hay SPA: la API existe para una app móvil, que lleva
        // su token en cada petición.
        //
        // Se deja fuera para que haya **un solo** camino de autenticación. Con
        // los dos, una misma petición puede entrar por token o por cookie, y
        // razonar sobre quién tiene acceso —y sobre CSRF— cuesta el doble sin
        // que nadie lo aproveche. Si algún día hay SPA propia, este es el
        // interruptor que hay que encender.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
