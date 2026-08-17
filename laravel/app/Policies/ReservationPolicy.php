<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Models\WishlistItem;

/**
 * La regla que la base no puede hacer cumplir: el dueño de una lista jamás
 * reserva en ella. La BD no conoce esa relación (reservations no sabe de
 * wishlists), así que este es el único lugar donde se sostiene.
 */
class ReservationPolicy
{
    public function create(User $user, WishlistItem $item): bool
    {
        $wishlist = $item->wishlist;

        // Reservarse un regalo a sí mismo no tiene sentido y además le
        // revelaría al dueño que la reserva existe.
        if ($wishlist->user_id === $user->id) {
            return false;
        }

        // Hay que poder ver la lista para poder regalar en ella.
        if ($user->cannot('view', $wishlist)) {
            return false;
        }

        // Lo que el dueño ya recibió deja de ofrecerse.
        if ($item->isReceived()) {
            return false;
        }

        // El chequeo final lo hace la base con el índice único; este solo
        // evita mostrar un botón que iba a fallar.
        return ! $item->reservations()->whereNotNull('active_flag')->exists();
    }

    /**
     * Soltar la reserva la puede solo quien reservó. Ni el dueño de la lista
     * —que no debería ni saber que existe— ni nadie más.
     */
    public function release(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id && $reservation->isActive();
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id;
    }
}
