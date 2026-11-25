<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La foto de una ficha propia, como sub-recurso del producto.
 *
 * Sigue el modelo de AvatarController, y por las mismas razones: la foto
 * viaja en multipart y no cabe en el json del resto de la API, quitarla se
 * dice con un DELETE en vez de un campo en null, y PUT porque cambiarla es
 * idempotente. También necesita PHP 8.4 para desarmar multipart en un PUT
 * (ver AvatarController).
 *
 * Solo la toca el autor de la ficha (ProductPolicy::updatePhoto). Si la ficha
 * está publicada, la foto nueva la ven todos los que la tienen en sus listas.
 */
class ProductImageController extends Controller
{
    /**
     * Devuelve la ficha entera y no 204: el nombre del archivo lo inventa el
     * servidor y el cliente necesita la URL nueva para pintarla.
     */
    public function store(Request $request, Product $product, ProductPhotoService $fotos): JsonResponse
    {
        $this->authorize('updatePhoto', $product);

        $request->validate([
            // Las mismas reglas que al crear el regalo con foto: contenido de
            // imagen de verdad, tres formatos que todo navegador muestra (nada
            // de svg) y lo que pesa una foto de celular.
            'image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ], [], ['image' => 'foto']);

        $fotos->replace($product, $request->file('image'));

        return $this->ficha($request, $product);
    }

    /**
     * Quitar una foto que no estaba no es un error: es el estado que se pedía.
     */
    public function destroy(Request $request, Product $product, ProductPhotoService $fotos): JsonResponse
    {
        $this->authorize('updatePhoto', $product);

        $fotos->remove($product);

        return $this->ficha($request, $product);
    }

    private function ficha(Request $request, Product $product): JsonResponse
    {
        return response()->json([
            'data' => ProductResource::make($product->fresh()->load('category'))->resolve($request),
        ]);
    }
}
