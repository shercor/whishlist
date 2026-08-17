<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductLike;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El «me gusta» a una ficha del catálogo.
 *
 * Se vuelve con back() a propósito: el botón vive dentro de los resultados de
 * una búsqueda, y redirigir a una ruta fija haría perder el término escrito.
 */
class ProductLikeController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('like', $product);

        try {
            ProductLike::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // 23000 acá solo puede ser el único de (product_id, user_id): el
            // usuario hizo doble clic. Ya está votado, que es lo que quería.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return back();
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('like', $product);

        $product->likes()->where('user_id', $request->user()->id)->delete();

        return back();
    }
}
