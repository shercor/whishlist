<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLike>
 */
class ProductLikeFactory extends Factory
{
    protected $model = ProductLike::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // ProductFactory ya nace público, que es lo correcto acá: los
            // privados no se votan, y generarlos haría pasar tests que la
            // policy rechaza en la aplicación real.
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
        ];
    }
}
