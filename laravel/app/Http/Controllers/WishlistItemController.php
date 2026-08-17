<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
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

        $resultados = $termino === ''
            ? Product::public()->latest()->limit(12)->get()
            : Product::visibleTo($request->user())->searchPrefix($termino)->limit(24)->get();

        $categories = Category::active()->orderBy('name')->get();

        return view('items.create', compact('wishlist', 'resultados', 'categories', 'termino'));
    }

    public function store(StoreWishlistItemRequest $request, Wishlist $wishlist): RedirectResponse
    {
        $this->authorize('manageItems', $wishlist);

        $product = $request->chosenProduct() ?? $this->createPrivateProduct($request);

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

        return redirect()->route('wishlists.show', $item->wishlist)
            ->with('status', $item->isReceived() ? 'Marcado como recibido.' : 'Vuelve a estar en la lista.');
    }

    /**
     * El producto que el usuario escribió a mano: privado, visible solo para
     * él, no entra al catálogo de nadie más.
     */
    private function createPrivateProduct(StoreWishlistItemRequest $request): Product
    {
        return Product::create([
            'category_id' => $request->integer('category_id'),
            'created_by_user_id' => $request->user()->id,
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'url' => $request->input('url'),
            'reference_price' => $request->input('reference_price'),
            'is_public' => false,
        ]);
    }
}
