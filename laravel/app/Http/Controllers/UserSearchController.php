<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Encontrar a otra persona para regalarle algo.
 *
 * La búsqueda mira únicamente el `username`. Nunca el nombre real ni el
 * correo: si mirara el nombre, la opción de mantenerlo privado no serviría de
 * nada, y si mirara el correo bastaría con probar direcciones para saber quién
 * está registrado.
 */
class UserSearchController extends Controller
{
    public function index(Request $request): View
    {
        $termino = trim($request->string('q')->toString());

        $usuarios = $termino === ''
            // Sin término no se lista a nadie: el directorio completo de la
            // plataforma no debe poder recorrerse de corrido.
            ? collect()
            : User::searchByUsername($termino)
                ->whereKeyNot($request->user()->id)
                ->withCount(['wishlists as public_wishlists_count' => fn ($query) => $query->public()])
                ->orderBy('username')
                ->limit(25)
                ->get();

        return view('users.index', compact('usuarios', 'termino'));
    }

    /**
     * El perfil público: solo las listas que el que mira puede alcanzar.
     *
     * Las privadas no se cuentan ni se insinúan. Decir «tiene 2 listas más que
     * no puedes ver» ya es contar algo sobre alguien que eligió no contarlo.
     */
    public function show(Request $request, User $user): View
    {
        $yo = $request->user();
        $esMiPerfil = $user->id === $yo->id;

        // Un perfil privado no muestra nada a quien no lo sigue. Se llega a la
        // página y se ve el arroba y el botón de seguir: ni una lista, ni
        // cuántas hay. Saber cuántas listas tiene alguien ya es saber algo.
        $puedeVer = $esMiPerfil || ! $user->is_private || $user->isFollowedBy($yo);

        return view('users.show', [
            'usuario' => $user,
            'wishlists' => $puedeVer
                ? $user->visibleWishlistsFor($yo)->withCount('items')->latest()->get()
                : collect(),
            'esMiPerfil' => $esMiPerfil,
            'puedeVer' => $puedeVer,
            'seguimiento' => $esMiPerfil ? null : $yo->followTo($user),
        ]);
    }
}
