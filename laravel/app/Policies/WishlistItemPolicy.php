<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WishlistItem;

/**
 * Los regalos los manda el dueño de la lista a la que pertenecen.
 */
class WishlistItemPolicy
{
    public function view(User $user, WishlistItem $item): bool
    {
        return $user->can('view', $item->wishlist);
    }

    public function update(User $user, WishlistItem $item): bool
    {
        return $item->wishlist->user_id === $user->id;
    }

    public function delete(User $user, WishlistItem $item): bool
    {
        return $item->wishlist->user_id === $user->id;
    }

    /**
     * Marcar "ya me llegó". Solo el dueño sabe eso.
     */
    public function markReceived(User $user, WishlistItem $item): bool
    {
        return $item->wishlist->user_id === $user->id;
    }
}
