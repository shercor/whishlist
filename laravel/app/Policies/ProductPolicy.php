<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        return $product->is_public || $product->created_by_user_id === $user->id;
    }

    /**
     * Solo se vota el catálogo público.
     *
     * Un producto privado lo ve una sola persona, así que su voto no ordenaría
     * nada para nadie: sería un «me gusta» a sí mismo. Y permitirlo dejaría un
     * contador visible en fichas que se supone que nadie más alcanza.
     */
    public function like(User $user, Product $product): bool
    {
        return $product->is_public;
    }
}
