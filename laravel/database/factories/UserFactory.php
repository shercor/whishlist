<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // No se usa fake()->userName() porque genera puntos y mayúsculas,
            // que el formato no admite: la factory quedaría produciendo
            // usuarios que la aplicación real rechazaría. El número lo hace
            // único sin depender de la suerte.
            'username' => Str::of(fake()->firstName())->ascii()->lower()
                ->replaceMatches('/[^a-z]/', '')->limit(15, '')
                ->append('_', (string) fake()->unique()->numberBetween(1000, 999999))
                ->toString(),
            'show_name' => false,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
