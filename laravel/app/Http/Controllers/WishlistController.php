<?php

namespace App\Http\Controllers;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Http\Requests\WishlistRequest;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Las listas vistas por su dueño. Ninguna consulta de este controlador toca
 * la tabla de reservas: es lo que mantiene la sorpresa.
 */
class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        $wishlists = $request->user()->wishlists()
            ->withCount('items')
            ->orderBy('event_date')
            ->latest()
            ->get();

        return view('wishlists.index', compact('wishlists'));
    }

    public function create(): View
    {
        return view('wishlists.create');
    }

    public function store(WishlistRequest $request): RedirectResponse
    {
        $wishlist = $request->user()->wishlists()->create([
            ...$request->safe()->only(['name', 'description', 'visibility', 'event_date']),
            'share_token' => $request->visibility()->needsShareToken() ? Str::random(32) : null,
        ]);

        return redirect()->route('wishlists.show', $wishlist)
            ->with('status', 'Lista creada. Ahora agrégale regalos.');
    }

    /**
     * La lista como la ve su dueño: sus regalos y nada sobre quién los tomó.
     */
    public function show(Request $request, Wishlist $wishlist): View
    {
        $this->authorize('update', $wishlist);

        $items = $wishlist->items()->ordered()->with('product')->get();

        return view('wishlists.show', compact('wishlist', 'items'));
    }

    public function edit(Wishlist $wishlist): View
    {
        $this->authorize('update', $wishlist);

        $pendientes = $wishlist->accesses()->pending()->with('user')->get();

        return view('wishlists.edit', compact('wishlist', 'pendientes'));
    }

    public function update(WishlistRequest $request, Wishlist $wishlist): RedirectResponse
    {
        $this->authorize('update', $wishlist);

        $visibility = $request->visibility();

        $wishlist->update([
            ...$request->safe()->only(['name', 'description', 'visibility', 'event_date']),
            // Al pasar a "por enlace" hay que tener token; al dejar de serlo,
            // el token viejo debe morir o el enlace seguiría abriendo la lista.
            'share_token' => $visibility->needsShareToken()
                ? ($wishlist->share_token ?? Str::random(32))
                : null,
        ]);

        return redirect()->route('wishlists.show', $wishlist)
            ->with('status', 'Lista actualizada.');
    }

    public function destroy(Wishlist $wishlist): RedirectResponse
    {
        $this->authorize('delete', $wishlist);

        $wishlist->delete();

        return redirect()->route('wishlists.index')
            ->with('status', 'Lista eliminada.');
    }

    /**
     * Listas públicas de otra gente, para encontrar a quién regalarle.
     */
    public function discover(Request $request): View
    {
        // Las listas públicas de un perfil privado no salen acá: solo las ven
        // sus seguidores, y «descubrir» es justo la pantalla de quien todavía
        // no sigue a nadie. Sin este filtro, marcar el perfil como privado no
        // serviría de nada.
        $wishlists = Wishlist::query()
            ->public()
            ->whereNot('user_id', $request->user()->id)
            ->whereHas('user', fn ($query) => $query->where('is_private', false))
            ->with('user')
            ->withCount('items')
            ->orderBy('event_date')
            ->paginate(12);

        return view('discover.index', compact('wishlists'));
    }

    /**
     * Entrada por el enlace secreto: conocer el token es el permiso.
     */
    public function openByLink(Request $request, string $token): RedirectResponse
    {
        // Ya no se acota a las listas «por enlace»: la privada también tiene
        // enlace, y es la puerta de quien no sigue al dueño.
        $wishlist = Wishlist::where('share_token', $token)->firstOrFail();

        $wishlist->unlockByLink();

        if ($wishlist->user_id !== $request->user()->id) {
            $this->recordLinkAccess($wishlist, $request->user());
        }

        return redirect()->route('gifts.show', $wishlist);
    }

    /**
     * Deja anotado que esta persona entró con el enlace.
     *
     * Antes el enlace solo desbloqueaba la sesión, y eso lo hacía invisible e
     * irrevocable: el dueño no tenía forma de saber quién había entrado ni de
     * echarlo. Anotado, aparece en la pantalla de accesos de la lista y se le
     * puede quitar.
     *
     * No pisa un acceso que ya exista: si a alguien lo invitaron y además abre
     * el enlace, el origen sigue siendo la invitación, que es la que manda
     * sobre cuánto dura.
     */
    private function recordLinkAccess(Wishlist $wishlist, User $user): void
    {
        $wishlist->accesses()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'status' => AccessRequestStatus::APPROVED->label(),
                'source' => AccessSource::LINK->label(),
                'responded_at' => now(),
            ]
        );
    }
}
