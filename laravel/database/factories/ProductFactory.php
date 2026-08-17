<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Por defecto crea un producto del catálogo curado: público y sin autor.
     * Lo que un usuario escribe a mano se pide con privateFor().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'created_by_user_id' => null,
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'url' => fake()->url(),
            'image_url' => null,
            'reference_price' => fake()->numberBetween(5, 500) * 1000,
            'currency' => 'CLP',
            'is_public' => true,
        ];
    }

    /**
     * Producto privado: solo lo ve quien lo creó, no ensucia el catálogo.
     */
    public function privateFor(User $user): static
    {
        return $this->state(fn () => [
            'created_by_user_id' => $user->id,
            'is_public' => false,
        ]);
    }
}
