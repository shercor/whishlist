<?php

namespace Tests\Feature;

use App\Enums\ItemPriority;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLike;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proponer productos al catálogo público.
 *
 * Se decidió publicación directa, sin moderación: lo que alguien marca como
 * compartido entra al catálogo de todos en el acto. La contrapartida es que la
 * marcha atrás tiene que existir y funcionar, porque es lo único que hay contra
 * una ficha publicada por error.
 *
 * Lo que **no** cambia: compartir la ficha de un producto no cuenta que tú lo
 * quieras. La lista y el regalo siguen siendo privados.
 */
class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function agregarRegalo(User $duenio, Wishlist $lista, array $extra = []): void
    {
        $this->actingAs($duenio)
            ->post(route('items.store', $lista), [
                'name' => 'Taza de greda de Pomaire',
                'category_id' => Category::factory()->create()->id,
                'priority' => ItemPriority::MEDIUM->label(),
                ...$extra,
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_a_handwritten_gift_stays_private_by_default(): void
    {
        $duenio = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $duenio->id]);

        $this->agregarRegalo($duenio, $lista);

        $producto = Product::where('name', 'Taza de greda de Pomaire')->firstOrFail();

        // Privado salvo que se pida: compartir no puede ser lo que pasa por no
        // marcar nada.
        $this->assertFalse((bool) $producto->is_public);
    }

    public function test_asking_to_share_puts_it_in_the_catalog_right_away(): void
    {
        $duenio = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $duenio->id]);

        $this->agregarRegalo($duenio, $lista, ['share_with_catalog' => '1']);

        $producto = Product::where('name', 'Taza de greda de Pomaire')->firstOrFail();

        $this->assertTrue((bool) $producto->is_public);
        $this->assertSame($duenio->id, $producto->created_by_user_id);

        // Y otra persona la ve en el catálogo.
        $extranio = User::factory()->create();
        $this->assertTrue(Product::visibleTo($extranio)->whereKey($producto->id)->exists());
        $this->assertTrue($extranio->can('view', $producto));
    }

    /**
     * Lo que se comparte es la ficha, no el deseo. Si compartir delatara que
     * quieres algo, nadie lo usaría.
     */
    public function test_sharing_the_product_does_not_share_your_wishlist(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $lista = Wishlist::factory()->create(['user_id' => $duenio->id]);

        $this->agregarRegalo($duenio, $lista, ['share_with_catalog' => '1']);

        $extranio = User::factory()->create();

        $this->assertFalse($extranio->can('view', $lista->fresh()));
        $this->assertCount(0, $duenio->visibleWishlistsFor($extranio));
    }

    public function test_a_shared_product_can_be_liked_by_anyone(): void
    {
        $autor = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $autor->id]);

        $this->agregarRegalo($autor, $lista, ['share_with_catalog' => '1']);

        $producto = Product::where('name', 'Taza de greda de Pomaire')->firstOrFail();
        $otra = User::factory()->create();

        $this->actingAs($otra)
            ->from(route('discover'))
            ->post(route('products.like', $producto))
            ->assertRedirect(route('discover'));

        $this->assertSame(1, $producto->likes()->count());
    }

    // --- La marcha atrás ------------------------------------------------------

    public function test_the_author_can_pull_their_product_out_of_the_catalog(): void
    {
        $autor = User::factory()->create();
        $producto = Product::factory()->create(['created_by_user_id' => $autor->id, 'is_public' => true]);

        ProductLike::factory()->for($producto)->create();

        $this->actingAs($autor)
            ->from(route('discover'))
            ->delete(route('products.unpublish', $producto))
            ->assertSessionHas('status');

        $producto->refresh();

        $this->assertFalse((bool) $producto->is_public);
        // Los votos se van con la publicación: si no, reaparecerían al volver
        // a publicarla.
        $this->assertSame(0, $producto->likes()->count());
    }

    /**
     * Retirarla no la borra: quien ya la había agregado a su lista sigue
     * teniendo su regalo.
     */
    public function test_pulling_it_out_does_not_empty_anyone_elses_list(): void
    {
        $autor = User::factory()->create();
        $producto = Product::factory()->create(['created_by_user_id' => $autor->id, 'is_public' => true]);

        $otra = User::factory()->create();
        $suLista = Wishlist::factory()->create(['user_id' => $otra->id]);
        $suRegalo = WishlistItem::factory()->for($suLista)->for($producto)->create();

        $this->actingAs($autor)->delete(route('products.unpublish', $producto));

        $this->assertNotNull($suRegalo->fresh());
        $this->assertNotNull($suRegalo->fresh()->product);
        $this->assertSame($producto->name, $suRegalo->fresh()->displayName());
    }

    public function test_nobody_can_pull_out_someone_elses_product(): void
    {
        $autor = User::factory()->create();
        $producto = Product::factory()->create(['created_by_user_id' => $autor->id, 'is_public' => true]);

        $this->actingAs(User::factory()->create())
            ->delete(route('products.unpublish', $producto))
            ->assertForbidden();

        $this->assertTrue((bool) $producto->fresh()->is_public);
    }

    /**
     * El catálogo curado —los productos de los seeders, sin autor— no lo retira
     * nadie desde la aplicación: nadie es su dueño.
     */
    public function test_the_curated_catalog_cannot_be_pulled_out(): void
    {
        $curado = Product::factory()->create(['created_by_user_id' => null, 'is_public' => true]);

        $this->actingAs(User::factory()->create())
            ->delete(route('products.unpublish', $curado))
            ->assertForbidden();

        $this->assertTrue((bool) $curado->fresh()->is_public);
    }
}
