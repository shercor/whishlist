<?php

namespace Database\Factories;

use App\Enums\AccessRequestStatus;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistAccess>
 */
class WishlistAccessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wishlist_id' => Wishlist::factory(),
            'user_id' => User::factory(),
            'status' => AccessRequestStatus::PENDING->label(),
            'message' => fake()->sentence(),
            'responded_at' => null,
        ];
    }

    /**
     * Un solo state para los cuatro estados: responded_at se deriva de si el
     * dueño ya contestó, para que no queden filas contradictorias.
     */
    public function status(AccessRequestStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->label(),
            'responded_at' => $status->isAwaitingResponse() ? null : now(),
        ]);
    }
}
