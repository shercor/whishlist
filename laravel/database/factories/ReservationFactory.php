<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Por defecto la reserva está viva: status activa y active_flag en 1, que
     * es lo que hace valer el índice único de una reserva activa por ítem.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wishlist_item_id' => WishlistItem::factory(),
            'user_id' => User::factory(),
            'status' => ReservationStatus::ACTIVE->label(),
            'expires_at' => now()->addDays(14),
            'released_at' => null,
            'note' => null,
            'active_flag' => 1,
        ];
    }

    /**
     * Reserva terminada: soltar active_flag es lo que libera el ítem.
     */
    public function released(ReservationStatus $status = ReservationStatus::CANCELLED): static
    {
        return $this->state(fn () => [
            'status' => $status->label(),
            'active_flag' => null,
            'released_at' => now(),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->released(ReservationStatus::FULFILLED);
    }

    /**
     * Sigue activa pero con el plazo vencido: es lo que debe encontrar el job
     * que libera reservas abandonadas.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function neverExpires(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }
}
