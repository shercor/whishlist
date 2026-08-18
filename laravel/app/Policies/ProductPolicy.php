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

    /**
     * Retirar del catálogo una ficha que uno mismo publicó.
     *
     * Los productos del catálogo curado —los de los seeders, sin autor— no se
     * retiran desde la aplicación: nadie es su dueño. Y una ficha ajena
     * tampoco, aunque sea pública: eso sería moderación, y esto no lo es.
     */
    public function unpublish(User $user, Product $product): bool
    {
        return $product->is_public
            && $product->created_by_user_id === $user->id;
    }
}
