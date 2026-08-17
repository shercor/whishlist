<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Desde Laravel 11 el controlador base ya no la trae. Acá se necesita en
     * casi todas las acciones, así que vive en la base y no repetida.
     */
    use AuthorizesRequests;
}
