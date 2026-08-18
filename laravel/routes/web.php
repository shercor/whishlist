<?php

use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GiftController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductLikeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WishlistItemController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('wishlists.index')
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // --- Mis listas, vistas por mí -----------------------------------------
    Route::get('/wishlists', [WishlistController::class, 'index'])->name('wishlists.index');
    Route::get('/wishlists/create', [WishlistController::class, 'create'])->name('wishlists.create');
    Route::post('/wishlists', [WishlistController::class, 'store'])->name('wishlists.store');
    Route::get('/wishlists/{wishlist}', [WishlistController::class, 'show'])->name('wishlists.show');
    Route::get('/wishlists/{wishlist}/edit', [WishlistController::class, 'edit'])->name('wishlists.edit');
    Route::put('/wishlists/{wishlist}', [WishlistController::class, 'update'])->name('wishlists.update');
    Route::delete('/wishlists/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlists.destroy');

    // --- Regalos dentro de mis listas --------------------------------------
    Route::get('/wishlists/{wishlist}/items/create', [WishlistItemController::class, 'create'])->name('items.create');
    Route::post('/wishlists/{wishlist}/items', [WishlistItemController::class, 'store'])->name('items.store');
    Route::patch('/items/{item}', [WishlistItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [WishlistItemController::class, 'destroy'])->name('items.destroy');
    Route::post('/items/{item}/received', [WishlistItemController::class, 'markReceived'])->name('items.received');

    // --- Perfil propio y búsqueda de personas -------------------------------
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/usuarios', [UserSearchController::class, 'index'])->name('users.search');
    // Las coincidencias mientras se escribe. Va antes de /u/{user} solo por
    // orden de lectura: no chocan, cuelgan de prefijos distintos.
    Route::get('/usuarios/sugerencias', [UserSearchController::class, 'suggest'])->name('users.suggest');
    // El perfil público cuelga de /u/ y no de la raíz: así un usuario nuevo
    // nunca puede chocar con una ruta de la aplicación.
    Route::get('/u/{user:username}', [UserSearchController::class, 'show'])->name('users.show');

    // --- Catálogo: votar las fichas bien hechas -----------------------------
    Route::post('/products/{product}/like', [ProductLikeController::class, 'store'])->name('products.like');
    Route::delete('/products/{product}/like', [ProductLikeController::class, 'destroy'])->name('products.unlike');

    // --- Listas de otros, para regalarles ----------------------------------
    Route::get('/discover', [WishlistController::class, 'discover'])->name('discover');
    Route::get('/gifts/{wishlist}', [GiftController::class, 'show'])->name('gifts.show');

    // Entrada por enlace secreto. Conocer el token es el permiso.
    Route::get('/s/{token}', [WishlistController::class, 'openByLink'])->name('shared.open');

    // --- Reservas -----------------------------------------------------------
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/items/{item}/reservation', [ReservationController::class, 'store'])->name('reservations.store');
    Route::delete('/items/{item}/reservation', [ReservationController::class, 'destroy'])->name('reservations.destroy');

    // --- Notificaciones -----------------------------------------------------
    Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificaciones/leidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    // Va después de /leidas para que esa palabra no se lea como un id.
    Route::get('/notificaciones/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    // --- Seguidores ---------------------------------------------------------
    Route::get('/seguidores', [FollowController::class, 'index'])->name('follows.index');
    Route::post('/u/{user:username}/seguir', [FollowController::class, 'store'])->name('follows.store');
    Route::delete('/u/{user:username}/seguir', [FollowController::class, 'destroy'])->name('follows.destroy');
    Route::delete('/u/{user:username}/seguidor', [FollowController::class, 'remove'])->name('follows.remove');
    Route::patch('/seguidores/{follow}', [FollowController::class, 'update'])->name('follows.update');

    // --- Solicitudes y reparto de acceso a listas privadas -------------------
    Route::get('/access', [AccessRequestController::class, 'index'])->name('access.index');
    Route::post('/wishlists/{wishlist}/access', [AccessRequestController::class, 'store'])->name('access.store');
    Route::patch('/access/{access}', [AccessRequestController::class, 'update'])->name('access.update');

    Route::get('/wishlists/{wishlist}/accesos', [AccessRequestController::class, 'manage'])->name('access.manage');
    Route::post('/wishlists/{wishlist}/accesos', [AccessRequestController::class, 'invite'])->name('access.invite');
    Route::delete('/wishlists/{wishlist}/accesos/{access}', [AccessRequestController::class, 'revoke'])->name('access.revoke');
});
