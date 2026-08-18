<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Retirar del catálogo una ficha propia.
 *
 * Publicar es directo, sin moderación, así que la marcha atrás no es opcional:
 * sin esto, una ficha compartida por error se queda en el catálogo de todo el
 * mundo y solo se arregla a mano en la base.
 *
 * Retirarla no la borra: el producto sigue existiendo, privado, y las listas de
 * quienes ya lo hubieran agregado lo siguen mostrando. Borrarlo les vaciaría un
 * regalo que ellos eligieron.
 */
class ProductPublicationController extends Controller
{
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('unpublish', $product);

        $product->update(['is_public' => false]);

        // Los votos se van con la publicación: un producto privado no se vota,
        // y guardarlos haría que reaparecieran si se volviera a publicar.
        $product->likes()->delete();

        return back()->with('status', 'Ficha retirada del catálogo. Sigue en tus listas, ahora privada.');
    }
}
