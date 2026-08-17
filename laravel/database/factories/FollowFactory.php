<?php

namespace Database\Factories;

use App\Enums\FollowStatus;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
class FollowFactory extends Factory
{
    protected $model = Follow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'followed_id' => User::factory(),
            // Pendiente por defecto: que un test tenga que decir explícitamente
            // «este seguimiento está aceptado» para que abra algo.
            'status' => FollowStatus::PENDING->label(),
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => FollowStatus::ACCEPTED->label(),
            'responded_at' => now(),
        ]);
    }

    public function between(User $follower, User $followed): static
    {
        return $this->state(fn () => [
            'follower_id' => $follower->id,
            'followed_id' => $followed->id,
        ]);
    }
}
