<?php

namespace Tests\Feature\Api;

use App\Enums\WishlistVisibility;
use App\Http\Resources\WishlistItemResource;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La sorpresa, en json.
 *
 * Es el test más importante de la API. En las vistas la protección venía de no
 * consultar las reservas; en json basta un campo de más en un Resource para
 * publicarlo todo, y no se ve mirando la pantalla. Si algo de esto se pone
 * rojo, hay un regalo arruinado.
 */
class ApiSurpriseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Wishlist, 3: WishlistItem, 4: Reservation}
     */
    private function listaReservada(): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $regalador = User::factory()->create([
            'name' => 'Ana Rojas',
            'username' => 'anarojas_7',
            'show_name' => true,
        ]);

        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id]);

        $item = WishlistItem::factory()->for($lista)->create();

        $reserva = Reservation::factory()
            ->for($item, 'wishlistItem')
            ->for($regalador)
            ->create(['note' => 'Se lo llevo el sábado']);

        return [$duenio, $regalador, $lista, $item, $reserva];
    }

    public function test_the_owner_gets_no_reservation_field_at_all(): void
    {
        [$duenio, , $lista] = $this->listaReservada();

        Sanctum::actingAs($duenio);

        $regalo = $this->getJson(route('api.v1.wishlists.show', $lista))
            ->assertOk()
            ->json('data.items.0');

        // Ni el booleano: saber que está tomado ya le dice que se lo van a
        // comprar. Por eso la clave no debe ni existir.
        $this->assertArrayNotHasKey('is_reserved', $regalo);
        $this->assertArrayNotHasKey('reserved_by_me', $regalo);
    }

    public function test_the_owner_never_sees_the_reserver_anywhere_in_the_payload(): void
    {
        [$duenio, , $lista] = $this->listaReservada();

        Sanctum::actingAs($duenio);

        $crudo = $this->getJson(route('api.v1.wishlists.show', $lista))->assertOk()->content();

        foreach (['Ana Rojas', 'anarojas_7', 'Se lo llevo el sábado', 'reservation'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $crudo, "«{$rastro}» viajó al dueño.");
        }
    }

    public function test_a_visitor_sees_that_it_is_taken_but_never_by_whom(): void
    {
        [, , $lista] = $this->listaReservada();

        Sanctum::actingAs(User::factory()->create());

        $respuesta = $this->getJson(route('api.v1.wishlists.show', $lista))->assertOk();
        $regalo = $respuesta->json('data.items.0');

        $this->assertTrue($regalo['is_reserved']);
        // No es suya: la tiene otra persona.
        $this->assertFalse($regalo['reserved_by_me']);

        $crudo = $respuesta->content();
        $this->assertStringNotContainsString('Ana Rojas', $crudo);
        $this->assertStringNotContainsString('anarojas_7', $crudo);
        $this->assertStringNotContainsString('Se lo llevo el sábado', $crudo);
    }

    public function test_the_person_who_reserved_it_knows_it_is_theirs(): void
    {
        [, $regalador, $lista] = $this->listaReservada();

        Sanctum::actingAs($regalador);

        $regalo = $this->getJson(route('api.v1.wishlists.show', $lista))
            ->assertOk()
            ->json('data.items.0');

        $this->assertTrue($regalo['is_reserved']);
        $this->assertTrue($regalo['reserved_by_me']);
    }

    /**
     * El Resource decide por su cuenta, sin depender de que el controlador se
     * acuerde de pasarle una bandera. Se comprueba sirviéndolo suelto.
     */
    public function test_the_resource_hides_reservations_by_itself(): void
    {
        [$duenio, , , $item] = $this->listaReservada();

        Sanctum::actingAs($duenio);

        $serializado = WishlistItemResource::make($item)->resolve(request());

        $this->assertArrayNotHasKey('is_reserved', $serializado);
    }

    /**
     * Y si no puede saber de quién es la lista, esconde. Es la dirección segura
     * del error: mostrar de menos se arregla, filtrar no se deshace.
     */
    public function test_an_item_with_no_reachable_owner_hides_reservations(): void
    {
        [, , , $item] = $this->listaReservada();

        $item->setRelation('wishlist', null);

        Sanctum::actingAs(User::factory()->create());

        $serializado = WishlistItemResource::make($item)->resolve(request());

        $this->assertArrayNotHasKey('is_reserved', $serializado);
    }

    /**
     * «Voy a regalar» es del interesado, así que ahí sí van sus propias
     * reservas. Lo que no puede llevar es el `user_id`.
     */
    public function test_my_reservations_carry_no_user_id(): void
    {
        [, $regalador] = $this->listaReservada();

        Sanctum::actingAs($regalador);

        $respuesta = $this->getJson(route('api.v1.reservations.index'))->assertOk();

        $this->assertCount(1, $respuesta->json('data'));
        $this->assertArrayNotHasKey('user_id', $respuesta->json('data.0'));
        $this->assertStringNotContainsString('user_id', $respuesta->content());
    }
}
