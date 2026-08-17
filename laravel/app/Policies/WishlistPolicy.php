<?php

namespace App\Policies;

use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;

class WishlistPolicy
{
    /**
     * Quién puede abrir la lista. Son cuatro caminos y ninguno más: ser el
     * dueño, que sea pública, tener el acceso aprobado, o haber llegado con el
     * enlace secreto.
     */
    public function view(User $user, Wishlist $wishlist): bool
    {
        if ($this->owns($user, $wishlist)) {
            return true;
        }

        if ($wishlist->visibilityEnum() === WishlistVisibility::PUBLIC) {
            return true;
        }

        if ($wishlist->isUnlockedByLink()) {
            return true;
        }

        return $wishlist->accesses()->approved()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    public function delete(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Agregar, editar o borrar regalos de la lista.
     */
    public function manageItems(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Aprobar o rechazar solicitudes de acceso.
     */
    public function respondAccess(User $user, Wishlist $wishlist): bool
    {
        return $this->owns($user, $wishlist);
    }

    /**
     * Pedir acceso solo tiene sentido sobre la lista privada de otro, y una
     * sola vez: la tabla tiene único (wishlist_id, user_id).
     */
    public function requestAccess(User $user, Wishlist $wishlist): bool
    {
        if ($this->owns($user, $wishlist)) {
            return false;
        }

        if ($wishlist->visibilityEnum()->isReachableWithoutApproval()) {
            return false;
        }

        return ! $wishlist->accesses()->where('user_id', $user->id)->exists();
    }

    private function owns(User $user, Wishlist $wishlist): bool
    {
        return $wishlist->user_id === $user->id;
    }
}
