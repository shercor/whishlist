<?php

namespace Tests\Feature\Api;

use App\Enums\ItemPriority;
use App\Enums\WishlistVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Notifications\ReservationExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El ciclo completo por la API, y los códigos que devuelve.
 *
 * Los códigos importan tanto como los datos: un cliente decide qué hacer
 * mirándolos, así que un 200 donde debía ir un 201 o un 409 es un error de la
 * misma clase que devolver el campo equivocado.
 */
class ApiCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wishlist_can_be_created_read_updated_and_deleted(): void
    {
        $duenio = User::factory()->create();
        Sanctum::actingAs($duenio);

        // Crear: 201 y el recurso.
        $creada = $this->postJson(route('api.v1.wishlists.store'), [
            'name' => 'Cumpleaños',
            'visibility' => WishlistVisibility::PRIVATE->label(),
        ])->assertCreated();

        $id = $creada->json('data.id');

        // Una privada nace con su enlace, y al dueño sí se le dice.
        $this->assertNotEmpty($creada->json('data.share_token'));

        $this->getJson(route('api.v1.wishlists.show', $id))
            ->assertOk()
            ->assertJsonPath('data.name', 'Cumpleaños')
            ->assertJsonPath('data.is_mine', true);

        $this->patchJson(route('api.v1.wishlists.update', $id), [
            'name' => 'Cumpleaños 2027',
            'visibility' => WishlistVisibility::PUBLIC->label(),
        ])->assertOk()->assertJsonPath('data.name', 'Cumpleaños 2027');

        // Al volverse pública, el enlace secreto muere.
        $this->assertNull(Wishlist::find($id)->share_token);

        // Borrar: 204, sin cuerpo.
        $this->deleteJson(route('api.v1.wishlists.destroy', $id))->assertNoContent();

        $this->getJson(route('api.v1.wishlists.show', $id))->assertNotFound();
    }

    public function test_a_gift_can_be_added_from_the_catalog_and_removed(): void
    {
        $duenio = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $duenio->id]);
        $producto = Product::factory()->create();

        Sanctum::actingAs($duenio);

        $creado = $this->postJson(route('api.v1.items.store', $lista), [
            'product_id' => $producto->id,
            'priority' => ItemPriority::HIGH->label(),
        ])->assertCreated();

        $itemId = $creado->json('data.id');

        $this->patchJson(route('api.v1.items.update', $itemId), [
            'alias' => 'El azul',
            'priority' => ItemPriority::LOW->label(),
        ])->assertOk()->assertJsonPath('data.name', 'El azul');

        $this->deleteJson(route('api.v1.items.destroy', $itemId))->assertNoContent();
    }

    public function test_a_handwritten_gift_creates_a_private_product(): void
    {
        $duenio = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $duenio->id]);

        Sanctum::actingAs($duenio);

        $this->postJson(route('api.v1.items.store', $lista), [
            'name' => 'Una tetera de greda',
            'category_id' => Category::factory()->create()->id,
            'priority' => ItemPriority::MEDIUM->label(),
        ])->assertCreated();

        $producto = Product::where('name', 'Una tetera de greda')->firstOrFail();

        $this->assertFalse((bool) $producto->is_public);
        $this->assertSame($duenio->id, $producto->created_by_user_id);
    }

    /**
     * «Ya me llegó» se pone con PUT y se quita con DELETE, y al ponerlo cierra
     * la reserva de quien lo iba a regalar.
     */
    public function test_the_receipt_subresource_toggles_and_closes_the_reservation(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();
        $reserva = Reservation::factory()->for($item, 'wishlistItem')->create();

        Sanctum::actingAs($duenio);

        $this->putJson(route('api.v1.items.receipt.store', $item))
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertFalse($reserva->fresh()->isActive());

        $this->deleteJson(route('api.v1.items.receipt.destroy', $item))
            ->assertOk()
            ->assertJsonPath('data.received', false);
    }

    public function test_reserving_and_letting_go(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        $regalador = User::factory()->create();
        Sanctum::actingAs($regalador);

        $creada = $this->postJson(route('api.v1.reservations.store'), [
            'wishlist_item_id' => $item->id,
            'note' => 'Lo compro el viernes',
        ])->assertCreated();

        $this->assertSame('Lo compro el viernes', $creada->json('data.note'));

        $this->getJson(route('api.v1.reservations.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson(route('api.v1.reservations.destroy', $creada->json('data.id')))
            ->assertNoContent();

        $this->assertSame(0, $item->reservations()->whereNotNull('active_flag')->count());
    }

    /**
     * Perder la carrera es 409 y no 422: lo que mandó el cliente era válido, lo
     * que cambió fue el estado del recurso mientras hablábamos.
     */
    public function test_reserving_something_already_taken_answers_409_or_403(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        Reservation::factory()->for($item, 'wishlistItem')->create();

        Sanctum::actingAs(User::factory()->create());

        // La policy corta antes de llegar a la base, así que hoy es 403. El 409
        // es para la carrera de verdad, que solo ocurre por milisegundos.
        $this->postJson(route('api.v1.reservations.store'), [
            'wishlist_item_id' => $item->id,
        ])->assertForbidden();
    }

    public function test_the_owner_cannot_reserve_in_their_own_list(): void
    {
        $duenio = User::factory()->create();
        $item = WishlistItem::factory()->for(
            Wishlist::factory()->create(['user_id' => $duenio->id])
        )->create();

        Sanctum::actingAs($duenio);

        $this->postJson(route('api.v1.reservations.store'), [
            'wishlist_item_id' => $item->id,
        ])->assertForbidden();

        $this->assertSame(0, $item->reservations()->count());
    }

    public function test_liking_is_idempotent_and_private_products_cannot_be_liked(): void
    {
        $usuario = User::factory()->create();
        $publico = Product::factory()->create();
        $privado = Product::factory()->privateFor($usuario)->create();

        Sanctum::actingAs($usuario);

        // Dos veces el mismo PUT: un voto, no dos, y ningún error.
        $this->putJson(route('api.v1.products.like.store', $publico))->assertNoContent();
        $this->putJson(route('api.v1.products.like.store', $publico))->assertNoContent();

        $this->assertSame(1, $publico->likes()->count());

        $this->putJson(route('api.v1.products.like.store', $privado))->assertForbidden();

        $this->deleteJson(route('api.v1.products.like.destroy', $publico))->assertNoContent();
        $this->assertSame(0, $publico->likes()->count());
    }

    public function test_following_and_unfollowing(): void
    {
        $yo = User::factory()->create();
        $otra = User::factory()->create(['is_private' => false]);

        Sanctum::actingAs($yo);

        $this->postJson(route('api.v1.follows.store', $otra))->assertCreated();

        $this->getJson(route('api.v1.follows.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.following');

        $this->deleteJson(route('api.v1.follows.destroy', $otra))->assertNoContent();

        $this->getJson(route('api.v1.follows.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data.following');
    }

    public function test_you_cannot_follow_yourself(): void
    {
        $yo = User::factory()->create();
        Sanctum::actingAs($yo);

        $this->postJson(route('api.v1.follows.store', $yo))->assertForbidden();
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $regalador = User::factory()->create();
        $regalador->notify(new ReservationExpiring(
            Reservation::factory()->for($regalador)->create(['expires_at' => now()->addDay()])
        ));

        Sanctum::actingAs($regalador);

        $lista = $this->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJsonPath('meta.unread', 1);

        $this->patchJson(route('api.v1.notifications.update', $lista->json('data.0.id')))
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $this->assertSame(0, $regalador->fresh()->unreadNotifications()->count());
    }

    /**
     * Compartir con el catálogo desde la API, y retirarlo.
     */
    public function test_a_gift_can_be_shared_with_the_catalog_and_pulled_back(): void
    {
        $autor = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $autor->id]);

        Sanctum::actingAs($autor);

        $this->postJson(route('api.v1.items.store', $lista), [
            'name' => 'Cuaderno de la feria',
            'category_id' => Category::factory()->create()->id,
            'priority' => ItemPriority::MEDIUM->label(),
            'share_with_catalog' => true,
        ])->assertCreated()->assertJsonPath('data.product.is_public', true);

        $producto = Product::where('name', 'Cuaderno de la feria')->firstOrFail();

        // Otra persona lo ve en el catálogo.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.products.show', $producto))->assertOk();

        // Y solo su autor lo retira.
        $this->deleteJson(route('api.v1.products.publication.destroy', $producto))->assertForbidden();

        Sanctum::actingAs($autor);
        $this->deleteJson(route('api.v1.products.publication.destroy', $producto))
            ->assertOk()
            ->assertJsonPath('data.is_public', false);
    }

    public function test_a_gift_stays_private_when_sharing_is_not_asked_for(): void
    {
        $autor = User::factory()->create();
        $lista = Wishlist::factory()->create(['user_id' => $autor->id]);

        Sanctum::actingAs($autor);

        $this->postJson(route('api.v1.items.store', $lista), [
            'name' => 'Algo muy mío',
            'category_id' => Category::factory()->create()->id,
            'priority' => ItemPriority::MEDIUM->label(),
        ])->assertCreated()->assertJsonPath('data.product.is_public', false);
    }

    public function test_validation_errors_come_back_as_422_with_field_names(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.wishlists.store'), ['visibility' => 'inventada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'visibility']);
    }
}
