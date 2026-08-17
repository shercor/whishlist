<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            // El contador lo mantiene la aplicación al asociar productos; la
            // factory lo deja en cero para no inventar un dato inconsistente.
            'products_count' => 0,
        ];
    }

    public function popular(int $count = 50): static
    {
        return $this->state(fn () => ['products_count' => $count]);
    }
}
