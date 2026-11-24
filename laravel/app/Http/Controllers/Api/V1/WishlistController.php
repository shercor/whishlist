<?php

namespace App\Http\Controllers\Api\V1;

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
 * Un solo `show` sirve al dueño y a quien va a regalar, y es el
 * `WishlistItemResource` el que decide qué campos ve cada uno. En la web son
 * dos controladores distintos porque son dos pantallas; acá partirlo en dos
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
     */
    public function discover(Request $request): AnonymousResourceCollection
    {
        return WishlistResource::collection(
            Wishlist::query()
                ->public()
                ->whereNot('user_id', $request->user()->id)
                ->whereHas('user', fn ($query) => $query->where('is_private', false))
                ->with('user')
                ->withCount('items')
                ->orderBy('event_date')
                ->paginate(20)
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

        $esMia = $wishlist->user_id === $request->user()->id;

        $items = $wishlist->items()->ordered()->with('product.category');

        // Los contadores de reserva se piden **solo** si quien mira no es el
        // dueño. No es una optimización: es que la consulta que los trae es
        // justamente la que el dueño no debe hacer.
        if (! $esMia) {
            $items->withCount([
                'reservations as reserved_count' => fn ($query) => $query->whereNotNull('active_flag'),
                'reservations as mine_count' => fn ($query) => $query->whereNotNull('active_flag')
                    ->where('user_id', $request->user()->id),
            ]);
        }

        $wishlist->setRelation('items', $items->get());
        $wishlist->load('user');

        return response()->json(['data' => WishlistResource::make($wishlist)->resolve($request)]);
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
