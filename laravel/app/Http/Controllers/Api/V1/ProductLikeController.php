<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLike;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El «me gusta» de una ficha del catálogo, como sub-recurso del producto.
 *
 * PUT y no POST: votar es idempotente —apretarlo dos veces deja un voto, no
 * dos—, y eso es exactamente lo que PUT promete. Así el cliente puede
 * reintentar sin miedo si se le cae la red.
 */
class ProductLikeController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorize('like', $product);

        try {
            ProductLike::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // 23000 acá solo puede ser el único de (product_id, user_id): ya
            // estaba votado, que es el estado que el cliente pedía.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        return response()->json(status: 204);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('like', $product);

        $product->likes()->where('user_id', $request->user()->id)->delete();

        return response()->json(status: 204);
    }
}
