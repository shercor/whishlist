<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El «me gusta» solo existe para ordenar el catálogo público.
 *
 * Un producto privado lo ve una sola persona: votarlo sería un «me gusta» a sí
 * mismo y además dejaría un contador en una ficha que se supone que nadie más
 * alcanza. La regla vive únicamente en la policy, así que este es su único
 * respaldo.
 */
class ProductLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_product_can_be_liked(): void
    {
        $usuario = User::factory()->create();
        $producto = Product::factory()->create();

        $this->actingAs($usuario)
            ->from(route('discover'))
            ->post(route('products.like', $producto))
            ->assertRedirect(route('discover'));

        $this->assertSame(1, $producto->likes()->count());
        $this->assertTrue($producto->fresh()->isLikedBy($usuario));
    }

    public function test_a_private_product_cannot_be_liked_even_by_its_author(): void
    {
        $autor = User::factory()->create();
        $producto = Product::factory()->privateFor($autor)->create();

        $this->actingAs($autor)
            ->post(route('products.like', $producto))
            ->assertForbidden();

        $this->assertSame(0, $producto->likes()->count());
    }

    public function test_a_private_product_of_someone_else_cannot_be_liked_either(): void
    {
        $autor = User::factory()->create();
        $curioso = User::factory()->create();
        $producto = Product::factory()->privateFor($autor)->create();

        $this->assertFalse($curioso->can('like', $producto));

        $this->actingAs($curioso)
            ->post(route('products.like', $producto))
            ->assertForbidden();
    }

    /**
     * Quitar el voto pasa por la misma puerta. Si solo se hubiera protegido el
     * store, un producto que dejó de ser público seguiría aceptando peticiones.
     */
    public function test_unliking_a_private_product_is_refused_too(): void
    {
        $autor = User::factory()->create();
        $producto = Product::factory()->privateFor($autor)->create();

        $this->actingAs($autor)
            ->delete(route('products.unlike', $producto))
            ->assertForbidden();
    }

    /**
     * El doble clic: el índice único de (product_id, user_id) rechaza el
     * segundo insert y el controlador se lo traga, porque el usuario ya
     * consiguió lo que quería. Sin esto sería un 500.
     */
    public function test_liking_twice_leaves_a_single_vote_and_no_error(): void
    {
        $usuario = User::factory()->create();
        $producto = Product::factory()->create();

        foreach (range(1, 2) as $ignorado) {
            $this->actingAs($usuario)
                ->from(route('discover'))
                ->post(route('products.like', $producto))
                ->assertRedirect(route('discover'));
        }

        $this->assertSame(1, $producto->likes()->count());
    }

    public function test_unliking_only_removes_your_own_vote(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $producto = Product::factory()->create();

        ProductLike::factory()->for($producto)->for($ana)->create();
        ProductLike::factory()->for($producto)->for($bruno)->create();

        $this->actingAs($ana)
            ->from(route('discover'))
            ->delete(route('products.unlike', $producto));

        $this->assertSame(1, $producto->likes()->count());
        $this->assertTrue($producto->fresh()->isLikedBy($bruno));
    }
}
