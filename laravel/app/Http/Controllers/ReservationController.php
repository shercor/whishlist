<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\WishlistItem;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    /**
     * Lo que yo voy a regalar. Nunca lo que me van a regalar a mí.
     */
    public function index(Request $request): View
    {
        $reservations = $request->user()->reservations()
            ->active()
            // Cinturón: los modelos ya sueltan la reserva cuando se borra el
            // regalo o su lista, pero si alguna vez quedara una suelta, esta
            // pantalla no puede ser lo que se rompa. Antes lo era, y dejaba a
            // la persona sin poder ni soltarla.
            ->whereHas('wishlistItem.wishlist')
            ->with(['wishlistItem.product.category', 'wishlistItem.wishlist.user'])
            ->orderBy('expires_at')
            ->get();

        return view('reservations.index', compact('reservations'));
    }

    public function store(Request $request, WishlistItem $item): RedirectResponse
    {
        $this->authorize('create', [Reservation::class, $item]);

        $validated = $request->validate(
            ['note' => ['nullable', 'string', 'max:300']],
            [],
            ['note' => 'nota']
        );

        $reservation = $this->reservations->reserve($item, $request->user(), $validated['note'] ?? null);

        // null significa que otra persona reservó entre que se pintó el botón
        // y se apretó. No es un error de la aplicación, es la carrera que el
        // índice único está para ganar.
        if ($reservation === null) {
            return back()->with('error', 'Alguien se te adelantó y ya lo reservó.');
        }

        return back()->with('status', 'Reservado. El dueño de la lista no se entera.');
    }

    /**
     * Soltar mi reserva. Se busca por el ítem y no por id de reserva para no
     * publicar identificadores de reservas ajenas en el HTML.
     */
    public function destroy(Request $request, WishlistItem $item): RedirectResponse
    {
        $reservation = $item->reservations()
            ->whereNotNull('active_flag')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->authorize('release', $reservation);

        $this->reservations->release($reservation);

        return back()->with('status', 'Soltaste la reserva. Vuelve a estar disponible.');
    }
}
