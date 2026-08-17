<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\FollowStatus;
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
            'source' => AccessSource::REQUEST->label(),
            'message' => $validated['message'] ?? null,
        ]);

        return redirect()->route('access.index')
            ->with('status', 'Pedido enviado. Te avisará cuando responda.');
    }

    /**
     * Quién entra a esta lista, y a quién más se le puede dar.
     *
     * Los candidatos salen de los seguidores del dueño y no de todos los
     * usuarios de la plataforma: repartir una lista privada entre desconocidos
     * no tiene sentido, y buscar personas desde acá obligaría a decidir cuánto
     * muestra ese buscador.
     */
    public function manage(Request $request, Wishlist $wishlist): View
    {
        $this->authorize('manageAccess', $wishlist);

        $accesos = $wishlist->accesses()->with('user')->latest()->get();
        $yaTienen = $accesos->pluck('user_id');

        return view('access.manage', [
            'wishlist' => $wishlist,
            'accesos' => $accesos,
            'invitables' => $request->user()->followers()
                ->whereKeyNot($yaTienen)
                ->orderBy('username')
                ->get(),
        ]);
    }

    /**
     * El dueño le da la lista a uno de sus seguidores, sin que se la pida.
     */
    public function invite(Request $request, Wishlist $wishlist): RedirectResponse
    {
        $this->authorize('manageAccess', $wishlist);

        $validated = $request->validate([
            // La regla exists no basta: hay que exigir que sea seguidor, o
            // bastaría con mandar cualquier id para colarse en la lista.
            'user_id' => [
                'required',
                Rule::exists('follows', 'follower_id')
                    ->where('followed_id', $request->user()->id)
                    ->where('status', FollowStatus::ACCEPTED->label()),
            ],
        ], [], ['user_id' => 'persona']);

        $wishlist->accesses()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'status' => AccessRequestStatus::APPROVED->label(),
                'source' => AccessSource::INVITATION->label(),
                'responded_at' => now(),
            ]
        );

        return back()->with('status', 'Invitación hecha. Ya puede ver la lista.');
    }

    /**
     * Echar a alguien de una lista.
     *
     * Se borra la fila en vez de marcarla revocada porque el único de
     * (wishlist_id, user_id) impide volver a invitar a quien ya tuvo acceso:
     * quedaría bloqueado para siempre por una fila vieja.
     */
    public function revoke(Wishlist $wishlist, WishlistAccess $access): RedirectResponse
    {
        $this->authorize('manageAccess', $wishlist);

        abort_if($access->wishlist_id !== $wishlist->id, 404);

        $access->delete();

        return back()->with('status', 'Acceso quitado.');
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
