<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\UserProductCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistItemController extends Controller
{
    /**
     * Pantalla de agregar regalo: buscador del catálogo arriba, formulario
     * para escribirlo a mano abajo.
     */
    public function create(Request $request, Wishlist $wishlist): View
    {
        $this->authorize('manageItems', $wishlist);

        $termino = trim($request->string('q')->toString());

        // bestFirst() es lo que hace que, entre tres fichas del mismo producto,
        // la más votada sea la que la gente ve primero.
        $resultados = $termino === ''
            ? Product::public()->with('category')->withMyLike($request->user())->bestFirst()->limit(12)->get()
            : Product::visibleTo($request->user())->searchPrefix($termino)->with('category')
                ->withMyLike($request->user())->bestFirst()->limit(24)->get();

        $categories = Category::active()->orderBy('name')->get();

        return view('items.create', compact('wishlist', 'resultados', 'categories', 'termino'));
    }

    public function store(
        StoreWishlistItemRequest $request,
        Wishlist $wishlist,
        UserProductCreator $productos,
    ): RedirectResponse {
        $this->authorize('manageItems', $wishlist);

        $product = $request->chosenProduct() ?? $productos->create(
            $request->safe()->only(['category_id', 'name', 'description', 'url', 'reference_price']),
            $request->file('image'),
            $request->user(),
            $request->boolean('share_with_catalog'),
        );

        $wishlist->items()->create([
            'product_id' => $product->id,
            'alias' => $request->input('alias'),
            'notes' => $request->input('notes'),
            'priority' => $request->priority()->label(),
            // Al final de la lista: la posición manual la ajusta el dueño.
            'position' => ($wishlist->items()->max('position') ?? 0) + 1,
        ]);

        return redirect()->route('wishlists.show', $wishlist)
            ->with('status', 'Regalo agregado.');
    }

    public function update(UpdateWishlistItemRequest $request, WishlistItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update($request->validated());

        return redirect()->route('wishlists.show', $item->wishlist)
            ->with('status', 'Regalo actualizado.');
    }

    public function destroy(WishlistItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $wishlist = $item->wishlist;
        $item->delete();

        return redirect()->route('wishlists.show', $wishlist)
            ->with('status', 'Regalo quitado de la lista.');
    }

    /**
     * "Ya me llegó": el ítem deja de ofrecerse a los demás.
     */
    public function markReceived(WishlistItem $item): RedirectResponse
    {
        $this->authorize('markReceived', $item);

        $item->update(['received_at' => $item->isReceived() ? null : now()]);

        // El regalo llegó, así que la reserva cumplió su función y se cierra.
        // Si no, quien lo reservó lo seguiría viendo en «Voy a regalar» hasta
        // que venciera el plazo, y el regalo quedaría bloqueado por una
        // reserva que ya no significa nada.
        if ($item->isReceived()) {
            $item->releaseActiveReservation(ReservationStatus::FULFILLED);
        }

        return redirect()->route('wishlists.show', $item->wishlist)
            ->with('status', $item->isReceived() ? 'Marcado como recibido.' : 'Vuelve a estar en la lista.');
    }
}
