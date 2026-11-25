<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Http\Resources\WishlistItemResource;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\UserProductCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los regalos de una lista.
 *
 * Colgar `store` de la lista y dejar `update`/`destroy` en plano no es un
 * descuido: crear un regalo necesita saber en qué lista va, y para editar uno
 * su id ya lo identifica sin ambigüedad. Es la misma forma que usan las rutas
 * de la web.
 */
class WishlistItemController extends Controller
{
    /** Regalos por página si el cliente no dice otra cosa. */
    private const POR_PAGINA = 20;

    /**
     * Los regalos de una lista, por página y en el orden de la lista.
     *
     * Sirve al dueño y a quien regala: qué campos trae cada regalo lo decide
     * WishlistItemResource, no este método. La lista que no se puede ver da un
     * 403 seco; quién es su dueño y si se le puede pedir lo dice `show`.
     */
    public function index(Request $request, Wishlist $wishlist): AnonymousResourceCollection
    {
        $this->authorize('view', $wishlist);

        $datos = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $porPagina = (int) ($datos['per_page'] ?? self::POR_PAGINA);

        $items = $wishlist->items()->ordered()->with('product.category');

        // Los contadores de reserva se piden **solo** si quien mira no es el
        // dueño. No es una optimización: es que la consulta que los trae es
        // justamente la que el dueño no debe hacer.
        if ($wishlist->user_id !== $request->user()->id) {
            $items->withCount([
                'reservations as reserved_count' => fn ($query) => $query->whereNotNull('active_flag'),
                'reservations as mine_count' => fn ($query) => $query->whereNotNull('active_flag')
                    ->where('user_id', $request->user()->id),
            ]);
        }

        $pagina = $items->paginate($porPagina)->withQueryString();

        // El Resource pregunta de quién es la lista en cada regalo. Ya se
        // sabe: sin esto, cada uno la volvería a pedir a la base.
        $pagina->getCollection()->each->setRelation('wishlist', $wishlist);

        return WishlistItemResource::collection($pagina);
    }

    public function store(
        StoreWishlistItemRequest $request,
        Wishlist $wishlist,
        UserProductCreator $productos,
    ): JsonResponse {
        $this->authorize('manageItems', $wishlist);

        $product = $request->chosenProduct() ?? $productos->create(
            $request->safe()->only(['category_id', 'name', 'description', 'url', 'reference_price']),
            $request->file('image'),
            $request->user(),
            $request->boolean('share_with_catalog'),
        );

        $item = $wishlist->items()->create([
            'product_id' => $product->id,
            'alias' => $request->input('alias'),
            'notes' => $request->input('notes'),
            'priority' => $request->priority()->label(),
            'position' => ($wishlist->items()->max('position') ?? 0) + 1,
        ]);

        return response()->json(
            ['data' => WishlistItemResource::make($item->load('product.category'))->resolve($request)],
            201
        );
    }

    public function update(UpdateWishlistItemRequest $request, WishlistItem $item): JsonResponse
    {
        $this->authorize('update', $item);

        $item->update($request->validated());

        return response()->json([
            'data' => WishlistItemResource::make($item->fresh()->load('product.category'))->resolve($request),
        ]);
    }

    public function destroy(WishlistItem $item): JsonResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        return response()->json(status: 204);
    }

    /**
     * «Ya me llegó» como sub-recurso y no como un campo del regalo.
     *
     * PUT lo marca recibido y DELETE lo desmarca, que es lo que un cliente
     * espera de algo que se pone y se quita. Un PATCH del ítem con
     * `received_at` obligaría a exponer una fecha que en realidad nadie elige,
     * y a confiar en que el cliente mande la de hoy.
     */
    public function markReceived(Request $request, WishlistItem $item): JsonResponse
    {
        $this->authorize('markReceived', $item);

        $item->update(['received_at' => now()]);

        // El regalo llegó: la reserva cumplió y se cierra, igual que en la web.
        $item->releaseActiveReservation(ReservationStatus::FULFILLED);

        return response()->json([
            'data' => WishlistItemResource::make($item->fresh()->load('product.category'))->resolve($request),
        ]);
    }

    public function unmarkReceived(Request $request, WishlistItem $item): JsonResponse
    {
        $this->authorize('markReceived', $item);

        $item->update(['received_at' => null]);

        return response()->json([
            'data' => WishlistItemResource::make($item->fresh()->load('product.category'))->resolve($request),
        ]);
    }
}
