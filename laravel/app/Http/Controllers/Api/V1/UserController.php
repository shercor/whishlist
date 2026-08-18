<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\WishlistResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Encontrar gente y ver su perfil.
 *
 * La búsqueda mira solo el `username`, nunca el nombre real ni el correo. Es la
 * misma regla que la web y por el mismo motivo: si buscara por nombre, la
 * opción de ocultarlo no serviría de nada, y por correo bastaría con probar
 * direcciones para saber quién está registrado.
 */
class UserController extends Controller
{
    private const MINIMO_PARA_BUSCAR = 3;

    public function index(Request $request): AnonymousResourceCollection
    {
        $termino = trim($request->string('q')->toString());

        // Sin término no se lista a nadie: el directorio completo de la
        // plataforma no debe poder recorrerse de corrido.
        if (mb_strlen(ltrim($termino, '@')) < self::MINIMO_PARA_BUSCAR) {
            return UserResource::collection(collect());
        }

        return UserResource::collection(
            User::searchByUsername($termino)
                ->whereKeyNot($request->user()->id)
                ->orderBy('username')
                ->paginate(20)
                ->withQueryString()
        );
    }

    /**
     * El perfil público de alguien, con las listas que quien pregunta puede
     * abrir de verdad. Las que no, no se cuentan ni se insinúan: decir «tiene
     * 2 listas más que no puedes ver» ya es contar algo sobre alguien que
     * eligió no contarlo.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $yo = $request->user();

        return response()->json([
            'data' => [
                ...UserResource::make($user)->resolve($request),
                'profile_open' => $user->profileIsVisibleTo($yo),
                'wishlists' => WishlistResource::collection(
                    $user->visibleWishlistsFor($yo)
                )->resolve($request),
            ],
        ]);
    }
}
