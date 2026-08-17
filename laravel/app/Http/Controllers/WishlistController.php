<?php

namespace App\Http\Controllers;

use App\Enums\WishlistVisibility;
use App\Http\Requests\WishlistRequest;
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
        $wishlists = Wishlist::query()
            ->public()
            ->whereNot('user_id', $request->user()->id)
            ->with('user')
            ->withCount('items')
            ->orderBy('event_date')
            ->paginate(12);

        return view('discover.index', compact('wishlists'));
    }

    /**
     * Entrada por el enlace secreto: conocer el token es el permiso.
     */
    public function openByLink(string $token): RedirectResponse
    {
        $wishlist = Wishlist::where('share_token', $token)
            ->where('visibility', WishlistVisibility::LINK->label())
            ->firstOrFail();

        $wishlist->unlockByLink();

        return redirect()->route('gifts.show', $wishlist);
    }
}
