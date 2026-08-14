<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Usuario administrador inicial
    |---------------------------------------------------------------------------
    |
    | Credenciales que usa Database\Seeders\AdminUserSeeder para dejar un
    | usuario con el que poder entrar recién levantado el proyecto.
    | Se lee vía config (y no env() directo) para que siga funcionando
    | con la configuración cacheada.
    |
    */

    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL', 'admin@whishlist.test'),
    'password' => env('ADMIN_PASSWORD', 'admin1234'),

];
