<?php

use App\Http\Controllers\Api\V1\AccessRequestController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductLikeController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\WishlistItemController;
use Illuminate\Support\Facades\Route;

/*
|-----------------------------------------------------------------------------
| API v1
|-----------------------------------------------------------------------------
|
| Versionada desde el primer día: en cuanto haya una app publicada, cambiar la
| forma de una respuesta rompe las versiones viejas que la gente no actualiza.
| Con el prefijo puesto, v2 puede convivir con v1 en vez de sustituirla.
|
| Los nombres de ruta llevan `api.v1.` para no chocar con los de la web, que
| usan los mismos sustantivos.
|
| Códigos: 201 al crear, 204 cuando no hay cuerpo que devolver, 409 cuando el
| recurso cambió de estado mientras hablábamos (perder la carrera por una
| reserva), 422 en validación, 403 sin permiso y 401 sin token.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // --- Entrar y salir ------------------------------------------------------
    // Crear un token es iniciar sesión; borrarlo, cerrarla.
    Route::post('tokens', [TokenController::class, 'store'])
        ->name('tokens.store')
        // El freno por correo e ip lo pone el controlador; este es el tope por
        // ip a secas, para que nadie use la API como ariete.
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
        Route::delete('tokens', [TokenController::class, 'destroyAll'])->name('tokens.destroy-all');

        // --- Mi perfil --------------------------------------------------------
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');

        // --- Personas ---------------------------------------------------------
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user:username}', [UserController::class, 'show'])->name('users.show');

        // --- Mis listas y las que puedo abrir ---------------------------------
        Route::apiResource('wishlists', WishlistController::class)->names('wishlists');

        // Los regalos cuelgan de su lista para crearlos, y van en plano para
        // editarlos: el id del regalo ya lo identifica sin ambigüedad.
        Route::post('wishlists/{wishlist}/items', [WishlistItemController::class, 'store'])->name('items.store');
        Route::patch('items/{item}', [WishlistItemController::class, 'update'])->name('items.update');
        Route::delete('items/{item}', [WishlistItemController::class, 'destroy'])->name('items.destroy');

        // «Ya me llegó» como sub-recurso que se pone y se quita.
        Route::put('items/{item}/receipt', [WishlistItemController::class, 'markReceived'])->name('items.receipt.store');
        Route::delete('items/{item}/receipt', [WishlistItemController::class, 'unmarkReceived'])->name('items.receipt.destroy');

        // --- Reservas ---------------------------------------------------------
        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');

        // --- Catálogo ---------------------------------------------------------
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        // PUT porque votar es idempotente: apretarlo dos veces deja un voto.
        Route::put('products/{product}/like', [ProductLikeController::class, 'store'])->name('products.like.store');
        Route::delete('products/{product}/like', [ProductLikeController::class, 'destroy'])->name('products.like.destroy');

        // --- Seguidores -------------------------------------------------------
        Route::get('follows', [FollowController::class, 'index'])->name('follows.index');
        Route::post('users/{user:username}/follow', [FollowController::class, 'store'])->name('follows.store');
        Route::delete('users/{user:username}/follow', [FollowController::class, 'destroy'])->name('follows.destroy');
        Route::delete('users/{user:username}/follower', [FollowController::class, 'removeFollower'])->name('follows.remove');
        Route::patch('follows/{follow}', [FollowController::class, 'update'])->name('follows.update');

        // --- Acceso a listas privadas -----------------------------------------
        Route::get('access', [AccessRequestController::class, 'index'])->name('access.index');
        Route::post('wishlists/{wishlist}/access', [AccessRequestController::class, 'store'])->name('access.store');
        Route::patch('access/{access}', [AccessRequestController::class, 'update'])->name('access.update');
        Route::post('wishlists/{wishlist}/invitations', [AccessRequestController::class, 'invite'])->name('access.invite');
        Route::delete('wishlists/{wishlist}/access/{access}', [AccessRequestController::class, 'revoke'])->name('access.revoke');

        // --- Notificaciones ---------------------------------------------------
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{notification}', [NotificationController::class, 'update'])->name('notifications.update');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    });
});
