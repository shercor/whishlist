<?php

namespace Tests\Feature\Api;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La API atacada por un extraño con token válido.
 *
 * Tener token no es tener permiso, y es el error fácil de cometer al pasar de
 * formularios a API: en la web el 403 lo daba la policy del controlador, y acá
 * hay que acordarse de llamarla en cada método nuevo. Este barrido es el que
 * avisa si a alguno se le olvida.
 */
class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stranger_with_a_valid_token_can_do_nothing_to_someone_elses_list(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();
        $acceso = WishlistAccess::factory()->create(['wishlist_id' => $lista->id]);

        $extranio = User::factory()->create();
        $follow = Follow::factory()->between($extranio, $duenio)->create();

        Sanctum::actingAs($extranio);

        $peticiones = [
            'ver la lista' => ['getJson', route('api.v1.wishlists.show', $lista), []],
            'editarla' => ['patchJson', route('api.v1.wishlists.update', $lista), ['name' => 'Secuestrada', 'visibility' => 'publica']],
            'borrarla' => ['deleteJson', route('api.v1.wishlists.destroy', $lista), []],
            'agregarle un regalo' => ['postJson', route('api.v1.items.store', $lista), ['name' => 'Colado', 'priority' => 'media']],
            'editar el regalo' => ['patchJson', route('api.v1.items.update', $item), ['priority' => 'alta']],
            'borrar el regalo' => ['deleteJson', route('api.v1.items.destroy', $item), []],
            'marcarlo recibido' => ['putJson', route('api.v1.items.receipt.store', $item), []],
            'desmarcarlo' => ['deleteJson', route('api.v1.items.receipt.destroy', $item), []],
            'invitar a alguien' => ['postJson', route('api.v1.access.invite', $lista), ['user_id' => $extranio->id]],
            'quitar un acceso' => ['deleteJson', route('api.v1.access.revoke', [$lista, $acceso]), []],
            'responder el acceso' => ['patchJson', route('api.v1.access.update', $acceso), ['status' => 'aprobada']],
            'responder el seguimiento' => ['patchJson', route('api.v1.follows.update', $follow), ['decision' => 'aceptar']],
        ];

        foreach ($peticiones as $nombre => [$metodo, $url, $datos]) {
            $estado = $this->$metodo($url, $datos)->status();

            $this->assertContains(
                $estado,
                [403, 404, 422],
                "«{$nombre}» respondió {$estado} a un extraño con token."
            );
        }

        $this->assertNotSame('Secuestrada', $lista->fresh()->name);
        $this->assertSame(1, $lista->items()->count());
        $this->assertNull($item->fresh()->received_at);
    }

    public function test_nobody_can_release_a_reservation_that_is_not_theirs(): void
    {
        $regalador = User::factory()->create();
        $reserva = Reservation::factory()->for($regalador)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.reservations.destroy', $reserva))->assertForbidden();

        $this->assertTrue($reserva->fresh()->isActive());
    }

    /**
     * Ni el dueño de la lista, que además no debería saber que existe.
     */
    public function test_the_list_owner_cannot_release_someone_elses_reservation(): void
    {
        $duenio = User::factory()->create();
        $item = WishlistItem::factory()->for(
            Wishlist::factory()->create(['user_id' => $duenio->id])
        )->create();
        $reserva = Reservation::factory()->for($item, 'wishlistItem')->create();

        Sanctum::actingAs($duenio);

        $this->deleteJson(route('api.v1.reservations.destroy', $reserva))->assertForbidden();
        $this->assertTrue($reserva->fresh()->isActive());
    }

    public function test_a_private_wishlist_is_not_readable_without_access(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.wishlists.show', $lista))->assertForbidden();
    }

    public function test_my_list_index_never_shows_other_peoples_lists(): void
    {
        $otra = User::factory()->create();
        Wishlist::factory()->count(3)->create(['user_id' => $otra->id]);

        $yo = User::factory()->create();
        Wishlist::factory()->create(['user_id' => $yo->id]);

        Sanctum::actingAs($yo);

        $this->getJson(route('api.v1.wishlists.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * El enlace secreto de una lista privada es su llave: sale para el dueño y
     * para nadie más, ni siquiera para quien tiene acceso concedido.
     */
    public function test_the_share_token_never_reaches_a_guest(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $invitado = User::factory()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);

        Follow::factory()->between($invitado, $duenio)->accepted()->create();
        WishlistAccess::factory()
            ->status(AccessRequestStatus::APPROVED)
            ->create([
                'wishlist_id' => $lista->id,
                'user_id' => $invitado->id,
                'source' => AccessSource::INVITATION->label(),
            ]);

        Sanctum::actingAs($invitado);

        $respuesta = $this->getJson(route('api.v1.wishlists.show', $lista))->assertOk();

        $this->assertArrayNotHasKey('share_token', $respuesta->json('data'));
        $this->assertStringNotContainsString($lista->share_token, $respuesta->content());
    }

    /**
     * Buscar personas por su nombre real no encuentra a nadie, igual que en la
     * web. Un endpoint json es donde más tienta añadir «búsqueda mejorada».
     */
    public function test_the_people_search_never_matches_a_real_name_or_email(): void
    {
        User::factory()->create([
            'username' => 'pelusa88',
            'name' => 'Ana Rojas',
            'show_name' => true,
            'email' => 'contacto@ejemplo.cl',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.users.index', ['q' => 'Ana Rojas']))->assertJsonCount(0, 'data');
        $this->getJson(route('api.v1.users.index', ['q' => 'contacto']))->assertJsonCount(0, 'data');
        $this->getJson(route('api.v1.users.index', ['q' => 'pelusa']))->assertJsonCount(1, 'data');
        // Con menos de tres letras no se lista a media plataforma.
        $this->getJson(route('api.v1.users.index', ['q' => 'pe']))->assertJsonCount(0, 'data');
    }

    public function test_a_private_product_is_not_readable_by_others(): void
    {
        $autor = User::factory()->create();
        $privado = Product::factory()->privateFor($autor)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.products.show', $privado))->assertForbidden();

        // Ni aparece en el catálogo.
        $this->getJson(route('api.v1.products.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $privado->id]);
    }
}
