<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Las categorías del catálogo. Un regalo escrito a mano exige una, así que el
 * cliente necesita de dónde elegirla.
 */
class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::active()->orderBy('name')->get());
    }
}
