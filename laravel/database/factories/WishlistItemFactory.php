<?php

namespace Database\Factories;

use App\Enums\ItemPriority;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wishlist_id' => Wishlist::factory(),
            'product_id' => Product::factory(),
            // En null se muestra el nombre del catálogo.
            'alias' => null,
            'notes' => null,
            'priority' => ItemPriority::MEDIUM->label(),
            'position' => 0,
            'received_at' => null,
        ];
    }

    public function priority(ItemPriority $priority): static
    {
        return $this->state(fn () => ['priority' => $priority->label()]);
    }

    public function at(int $position): static
    {
        return $this->state(fn () => ['position' => $position]);
    }

    /**
     * El dueño ya lo recibió, así que deja de ofrecerse a los demás.
     */
    public function received(): static
    {
        return $this->state(fn () => ['received_at' => now()->subDay()]);
    }
}
