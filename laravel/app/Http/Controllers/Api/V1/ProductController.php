<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * El catálogo.
 *
 * Solo lectura: hoy las fichas públicas las crean los seeders, y si un día los
 * usuarios pueden proponer productos habrá que decidir antes si eso pasa por
 * moderación (HANDOFF, sección 6). Mientras no esté decidido, no se expone un
 * POST que después habría que cambiar de forma.
 */
class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $termino = trim($request->string('q')->toString());

        $productos = Product::query()
            ->visibleTo($request->user())
            ->with('category')
            // bestFirst trae el withCount('likes') y ordena por votos; withMyLike
            // añade si este usuario ya votó. Los dos alimentan al Resource.
            ->bestFirst()
            ->withMyLike($request->user());

        if ($termino !== '') {
            $productos->search($termino);
        }

        return ProductResource::collection($productos->paginate(20)->withQueryString());
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json([
            'data' => ProductResource::make($product->load('category'))->resolve($request),
        ]);
    }
}
