<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retirar del catálogo una ficha propia.
 *
 * Publicar es directo —no hay moderación—, así que hace falta la marcha atrás:
 * sin esto, una ficha compartida por error se quedaba en el catálogo de todo el
 * mundo y solo se arreglaba a mano en la base.
 *
 * Retirarla no la borra. El producto sigue existiendo, privado, y las listas de
 * quienes ya lo hubieran agregado lo siguen mostrando: borrarlo les vaciaría un
 * regalo que ellos eligieron.
 */
class ProductPublicationController extends Controller
{
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('unpublish', $product);

        $product->update(['is_public' => false]);

        // Los votos que juntó mientras estuvo público se van con la
        // publicación: un producto privado no se vota, y dejarlos guardados
        // haría que reaparecieran si se volviera a publicar.
        $product->likes()->delete();

        return response()->json([
            'data' => ProductResource::make($product->fresh()->load('category'))->resolve($request),
        ]);
    }
}
