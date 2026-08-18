<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\WishlistItem;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lo que voy a regalar.
 *
 * A diferencia de la web, acá la reserva **sí** es un recurso con id propio y
 * se suelta con `DELETE /reservations/{id}`. En el HTML se evitaba publicar
 * ids de reserva porque el mismo documento lo veía cualquiera; en la API cada
 * respuesta va a una sola persona y solo contiene sus reservas, así que su
 * propio id no le cuenta nada que no sepa.
 */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReservationResource::collection(
            $request->user()->reservations()
                ->active()
                ->whereHas('wishlistItem.wishlist')
                ->with(['wishlistItem.product.category', 'wishlistItem.wishlist.user'])
                ->orderBy('expires_at')
                ->paginate(20)
        );
    }

    /**
     * Reservar. El regalo va en el cuerpo y no en la ruta: lo que se crea es
     * una reserva, y el regalo es uno de sus datos.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'wishlist_item_id' => ['required', 'integer', 'exists:wishlist_items,id'],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], ['note' => 'nota']);

        $item = WishlistItem::findOrFail($datos['wishlist_item_id']);

        $this->authorize('create', [Reservation::class, $item]);

        $reserva = $this->reservations->reserve($item, $request->user(), $datos['note'] ?? null);

        // Otra persona reservó entre la comprobación y el insert. 409 y no 422:
        // lo que mandó el cliente era válido, es el estado del recurso el que
        // cambió mientras hablábamos.
        if ($reserva === null) {
            return response()->json([
                'message' => 'Alguien se te adelantó y ya lo reservó.',
            ], 409);
        }

        return response()->json(
            ['data' => ReservationResource::make($reserva->load('wishlistItem.product.category'))->resolve($request)],
            201
        );
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->authorize('release', $reservation);

        $this->reservations->release($reservation);

        return response()->json(status: 204);
    }
}
