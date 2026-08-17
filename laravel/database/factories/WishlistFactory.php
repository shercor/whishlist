<?php

namespace Database\Factories;

use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wishlist>
 */
class WishlistFactory extends Factory
{
    /**
     * Nace privada, igual que el default de la migración: si un test no elige
     * visibilidad, que no exponga nada por accidente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'visibility' => WishlistVisibility::PRIVATE->label(),
            // Lo decide el enum, igual que en el state de visibilidad: si se
            // dejara fijo en null, la privada por defecto y la privada creada
            // con ->visibility(PRIVATE) saldrían distintas.
            'share_token' => WishlistVisibility::PRIVATE->needsShareToken() ? Str::random(32) : null,
            'event_date' => null,
        ];
    }

    /**
     * Un solo state para las tres visibilidades: así el token de enlace nunca
     * se olvida ni sobra, lo decide el propio enum.
     */
    public function visibility(WishlistVisibility $visibility): static
    {
        return $this->state(fn () => [
            'visibility' => $visibility->label(),
            'share_token' => $visibility->needsShareToken() ? Str::random(32) : null,
        ]);
    }

    public function forEvent(string $date): static
    {
        return $this->state(fn () => ['event_date' => $date]);
    }
}
