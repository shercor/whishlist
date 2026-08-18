<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Http\Resources\WishlistItemResource;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\PrivateProductCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(
        StoreWishlistItemRequest $request,
        Wishlist $wishlist,
        PrivateProductCreator $productos,
    ): JsonResponse {
        $this->authorize('manageItems', $wishlist);

        $product = $request->chosenProduct() ?? $productos->create(
            $request->safe()->only(['category_id', 'name', 'description', 'url', 'reference_price']),
            $request->file('image'),
            $request->user(),
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
