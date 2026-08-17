<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
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
    /**
     * Con menos de tres letras no se sugiere nada: «a» devolvería a media
     * plataforma, que es justo lo que la búsqueda por arroba evita.
     */
    private const MINIMO_PARA_SUGERIR = 3;

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
     * Las coincidencias mientras se escribe, en JSON.
     *
     * Mismas reglas que la búsqueda de siempre —solo `username`, nunca el
     * nombre ni el correo—: este endpoint no es una puerta de atrás. La
     * pantalla completa sigue funcionando sin javascript, esto solo adelanta
     * el resultado.
     *
     * Devuelve las iniciales y el tono además de la foto para que quien no
     * tiene avatar se dibuje igual que en el resto de la aplicación, sin que
     * el javascript tenga que recalcularlos por su cuenta.
     */
    public function suggest(Request $request): JsonResponse
    {
        $termino = trim($request->string('q')->toString());

        // Menos de tres caracteres devuelve vacío en vez de medio padrón.
        if (mb_strlen(ltrim($termino, '@')) < self::MINIMO_PARA_SUGERIR) {
            return response()->json(['usuarios' => []]);
        }

        $usuarios = User::searchByUsername($termino)
            ->whereKeyNot($request->user()->id)
            ->withCount(['wishlists as public_wishlists_count' => fn ($query) => $query->public()])
            ->orderBy('username')
            ->limit(8)
            ->get();

        return response()->json([
            'usuarios' => $usuarios->map(fn (User $persona) => [
                'handle' => $persona->handle(),
                // publicName() y no name: respeta a quien oculta su nombre.
                'nombre' => $persona->show_name ? $persona->name : null,
                'avatar' => $persona->avatarSrc(),
                'iniciales' => $persona->initials(),
                'tono' => $persona->avatarHue(),
                'listas' => $persona->public_wishlists_count,
                'url' => route('users.show', $persona),
            ]),
        ]);
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
