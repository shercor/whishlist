<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FollowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WishlistRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\WishlistResource;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/**
 * Listas de deseos.
 *
 * Un solo `show` sirve al dueño y a quien va a regalar. Los regalos no vienen
 * dentro: se piden paginados a `GET /wishlists/{id}/items`, donde el
 * `WishlistItemResource` decide qué campos ve cada uno. En la web son dos
 * controladores distintos porque son dos pantallas; acá partirlo en dos
 * endpoints obligaría al cliente a saber de antemano si la lista es suya, y
 * dejaría la regla de la sorpresa escrita en dos sitios.
 */
class WishlistController extends Controller
{
    /**
     * Mis listas. Las de otros se piden por su perfil.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WishlistResource::collection(
            $request->user()->wishlists()
                ->withCount('items')
                ->orderBy('event_date')
                ->latest()
                ->paginate(20)
        );
    }

    /**
     * Listas públicas de otra gente, para quien busca a quién regalarle.
     *
     * Mismo filtro que la web: las de un perfil privado no salen, porque solo
     * sus seguidores deben verlas y esta es la pantalla de quien no sigue a nadie.
     * Tampoco las vacías.
     *
     * Con `q`, solo las que tienen esas letras en su nombre o en el usuario de
     * su dueño. El orden pone primero lo que más sirve: las de gente que sigo,
     * después los eventos que vienen (el más cercano primero), las listas sin
     * fecha y al final los eventos que ya pasaron (el más reciente primero).
     */
    public function discover(Request $request): AnonymousResourceCollection
    {
        $yo = $request->user();
        $hoy = now()->toDateString();
        $termino = trim($request->string('q')->toString());

        return WishlistResource::collection(
            Wishlist::query()
                ->public()
                ->whereNot('user_id', $yo->id)
                ->whereHas('user', fn ($query) => $query->where('is_private', false))
                // Una lista vacía no tiene nada que regalar todavía.
                ->has('items')
                ->when($termino !== '', function ($query) use ($termino) {
                    $parecido = '%'.addcslashes(ltrim($termino, '@'), '%_\\').'%';
                    $query->where(fn ($query) => $query
                        ->where('name', 'like', $parecido)
                        ->orWhereHas('user', fn ($query) => $query->where('username', 'like', $parecido)));
                })
                ->with('user')
                ->withCount('items')
                ->orderByRaw(
                    'case when exists (select 1 from follows where follows.followed_id = wishlists.user_id'
                    .' and follows.follower_id = ? and follows.status = ?) then 0 else 1 end',
                    [$yo->id, FollowStatus::ACCEPTED->label()]
                )
                ->orderByRaw(
                    'case when event_date >= ? then 0 when event_date is null then 1 else 2 end',
                    [$hoy]
                )
                ->orderByRaw('case when event_date >= ? then event_date end asc', [$hoy])
                ->orderByDesc('event_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
        );
    }

    public function store(WishlistRequest $request): JsonResponse
    {
        $wishlist = $request->user()->wishlists()->create([
            ...$request->safe()->only(['name', 'description', 'visibility', 'event_date']),
            'share_token' => $request->visibility()->needsShareToken() ? Str::random(32) : null,
        ]);

        return response()->json(
            ['data' => WishlistResource::make($wishlist)->resolve($request)],
            201
        );
    }

    public function show(Request $request, Wishlist $wishlist): JsonResponse
    {
        $yo = $request->user();

        // Igual que la pantalla de la web: de quién es y si se le puede pedir,
        // pero nunca su nombre, o probando ids se sabría qué listas tiene.
        if ($yo->cannot('view', $wishlist)) {
            return response()->json([
                'message' => 'Esta lista es privada.',
                'owner' => UserResource::make($wishlist->user)->resolve($request),
                'access_status' => $wishlist->accesses()->where('user_id', $yo->id)->value('status'),
                'can_request' => $yo->can('requestAccess', $wishlist),
            ], 403);
        }

        // Sin los regalos: una lista puede tener muchos, y vienen por página en
        // `GET /wishlists/{id}/items`. Va la cuenta, para que el cliente sepa
        // cuántos son antes de pedirlos.
        $wishlist->loadCount('items');
        $wishlist->load('user');

        return response()->json(['data' => WishlistResource::make($wishlist)->resolve($request)]);
    }

    /**
     * Abrir una lista con su enlace secreto. Devuelve la lista sin sus regalos:
     * la app la pide después por su id, como cualquier otra.
     */
    public function openByLink(Request $request, string $token): JsonResponse
    {
        $wishlist = Wishlist::where('share_token', $token)->firstOrFail();

        $wishlist->recordLinkAccess($request->user());

        return response()->json(['data' => WishlistResource::make($wishlist->load('user'))->resolve($request)]);
    }

    public function update(WishlistRequest $request, Wishlist $wishlist): JsonResponse
    {
        $this->authorize('update', $wishlist);

        $visibility = $request->visibility();

        $wishlist->update([
            ...$request->safe()->only(['name', 'description', 'visibility', 'event_date']),
            // Igual que en la web: al pasar a privada hace falta token, y al
            // volverla pública el token viejo tiene que morir o el enlace
            // seguiría abriendo la lista.
            'share_token' => $visibility->needsShareToken()
                ? ($wishlist->share_token ?? Str::random(32))
                : null,
        ]);

        return response()->json(['data' => WishlistResource::make($wishlist->fresh())->resolve($request)]);
    }

    public function destroy(Wishlist $wishlist): JsonResponse
    {
        $this->authorize('delete', $wishlist);

        $wishlist->delete();

        return response()->json(status: 204);
    }
}
