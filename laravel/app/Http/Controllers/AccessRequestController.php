<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Solicitudes para ver una lista privada.
 *
 * Hoy se llega a una lista privada por su URL: quien la abre recibe un 403 con
 * el botón de pedir acceso. Queda pendiente decidir cómo buscar a una persona
 * sin conocer su lista (ver HANDOFF, sección 6).
 */
class AccessRequestController extends Controller
{
    /**
     * Las solicitudes que me hicieron a mí, más las que yo hice.
     */
    public function index(Request $request): View
    {
        $recibidas = WishlistAccess::query()
            ->whereIn('wishlist_id', $request->user()->wishlists()->select('id'))
            ->with(['user', 'wishlist'])
            ->latest()
            ->get();

        $enviadas = $request->user()->accessRequests()
            ->with('wishlist.user')
            ->latest()
            ->get();

        return view('access.index', compact('recibidas', 'enviadas'));
    }

    public function store(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $this->authorize('requestAccess', $wishlist);

        $validated = $request->validate(
            ['message' => ['nullable', 'string', 'max:300']],
            [],
            ['message' => 'mensaje']
        );

        $wishlist->accesses()->create([
            'user_id' => $request->user()->id,
            'status' => AccessRequestStatus::PENDING->label(),
            'message' => $validated['message'] ?? null,
        ]);

        return redirect()->route('access.index')
            ->with('status', 'Pedido enviado. Te avisará cuando responda.');
    }

    /**
     * El dueño responde: aprobar, rechazar o revocar un acceso que ya dio.
     */
    public function update(Request $request, WishlistAccess $access): RedirectResponse
    {
        $this->authorize('respondAccess', $access->wishlist);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AccessRequestStatus::APPROVED->label(),
                AccessRequestStatus::REJECTED->label(),
                AccessRequestStatus::REVOKED->label(),
            ])],
        ]);

        $access->update([
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);

        return back()->with('status', 'Solicitud respondida.');
    }
}
