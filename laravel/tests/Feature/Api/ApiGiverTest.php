<?php

namespace Tests\Feature\Api;

use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lo que necesita la app para las pantallas de quien regala: «Descubrir», el
 * botón de seguir de un perfil y soltar una reserva desde la lista.
 */
class ApiGiverTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_lists_only_public_lists_of_other_open_profiles(): void
    {
        $yo = User::factory()->create();
        $abierta = User::factory()->create(['is_private' => false]);
        $cerrada = User::factory()->create(['is_private' => true]);

        $visible = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $abierta->id, 'name' => 'Cumple de Bruno']);
        WishlistItem::factory()->count(2)->for($visible)->create();
        Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $abierta->id]);
        // Pública pero vacía: todavía no tiene nada que regalar.
        Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $abierta->id]);
        Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $cerrada->id]);
        Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $yo->id]);

        Sanctum::actingAs($yo);

        $respuesta = $this->getJson(route('api.v1.discover'))->assertOk();

        $this->assertSame([$visible->id], array_column($respuesta->json('data'), 'id'));
        $this->assertSame($abierta->username, $respuesta->json('data.0.owner.username'));
        $this->assertSame(2, $respuesta->json('data.0.items_count'));
    }

    public function test_discover_puts_people_i_follow_first_then_upcoming_then_undated_then_past(): void
    {
        $this->travelTo('2026-11-25 12:00:00');
        $yo = User::factory()->create();
        $sigo = User::factory()->create(['is_private' => false]);
        $otra = User::factory()->create(['is_private' => false]);
        Follow::factory()->between($yo, $sigo)->accepted()->create();

        $publica = fn (User $dueno, ?string $fecha) => Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->has(WishlistItem::factory(), 'items')
            ->create(['user_id' => $dueno->id, 'event_date' => $fecha]);

        $pasadaReciente = $publica($otra, '2026-11-20');
        $sinFecha = $publica($otra, null);
        $lejana = $publica($otra, '2027-03-01');
        $pasadaAntigua = $publica($otra, '2026-01-10');
        $deHoy = $publica($otra, '2026-11-25');
        $deQuienSigo = $publica($sigo, null);

        Sanctum::actingAs($yo);

        $this->assertSame(
            [$deQuienSigo->id, $deHoy->id, $lejana->id, $sinFecha->id, $pasadaReciente->id, $pasadaAntigua->id],
            array_column($this->getJson(route('api.v1.discover'))->assertOk()->json('data'), 'id')
        );
    }

    public function test_discover_searches_by_list_name_or_owner_username(): void
    {
        $yo = User::factory()->create();
        $bruno = User::factory()->create(['is_private' => false, 'username' => 'bruno_soto']);
        $carla = User::factory()->create(['is_private' => false, 'username' => 'carla']);

        $publica = fn (User $dueno, string $nombre) => Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->has(WishlistItem::factory(), 'items')
            ->create(['user_id' => $dueno->id, 'name' => $nombre]);

        $deBruno = $publica($bruno, 'Navidad');
        $cumple = $publica($carla, 'Mi cumpleaños');
        $publica($carla, 'Casa nueva');

        Sanctum::actingAs($yo);

        $ids = fn (string $q) => array_column(
            $this->getJson(route('api.v1.discover', ['q' => $q]))->assertOk()->json('data'),
            'id'
        );

        $this->assertSame([$cumple->id], $ids('cumple'));
        $this->assertSame([$deBruno->id], $ids('@bruno'));
        // Un comodín de LIKE se busca como letra, no como «cualquier cosa».
        $this->assertSame([], $ids('%'));
    }

    public function test_a_profile_says_how_i_follow_it(): void
    {
        $yo = User::factory()->create();
        $privada = User::factory()->create(['is_private' => true]);
        $publica = User::factory()->create(['is_private' => false]);

        Sanctum::actingAs($yo);

        $this->getJson(route('api.v1.users.show', $privada))
            ->assertOk()
            ->assertJsonPath('data.is_private', true)
            ->assertJsonPath('data.follow_status', null)
            ->assertJsonPath('data.profile_open', false);

        Follow::factory()->between($yo, $privada)->create();
        Follow::factory()->between($yo, $publica)->accepted()->create();

        $this->getJson(route('api.v1.users.show', $privada))
            ->assertJsonPath('data.follow_status', 'pendiente');
        $this->getJson(route('api.v1.users.show', $publica))
            ->assertJsonPath('data.is_private', false)
            ->assertJsonPath('data.follow_status', 'aceptado');
    }

    public function test_whoever_reserved_a_gift_gets_the_id_to_release_it(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $yo = User::factory()->create();
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $mio = WishlistItem::factory()->for($lista)->create(['position' => 1]);
        $ajeno = WishlistItem::factory()->for($lista)->create(['position' => 2]);
        $reserva = Reservation::factory()->for($mio, 'wishlistItem')->for($yo)->create();
        Reservation::factory()->for($ajeno, 'wishlistItem')->create();

        Sanctum::actingAs($yo);

        $regalos = collect($this->getJson(route('api.v1.items.index', $lista))->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertSame($reserva->id, $regalos[$mio->id]['my_reservation_id']);
        // La reserva de otra persona no entrega su id.
        $this->assertTrue($regalos[$ajeno->id]['is_reserved']);
        $this->assertNull($regalos[$ajeno->id]['my_reservation_id']);

        $this->deleteJson(route('api.v1.reservations.destroy', $reserva))->assertNoContent();
    }

    public function test_my_reservations_say_whose_list_they_are_from(): void
    {
        $duenio = User::factory()->create(['is_private' => false, 'username' => 'bruno']);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id, 'name' => 'Cumple de Bruno']);
        $item = WishlistItem::factory()->for($lista)->create();
        $yo = User::factory()->create();
        Reservation::factory()->for($item, 'wishlistItem')->for($yo)->create();

        Sanctum::actingAs($yo);

        $this->getJson(route('api.v1.reservations.index'))
            ->assertOk()
            ->assertJsonPath('data.0.wishlist.id', $lista->id)
            ->assertJsonPath('data.0.wishlist.name', 'Cumple de Bruno')
            ->assertJsonPath('data.0.wishlist.owner.username', 'bruno')
            // Ni el enlace secreto de una lista ajena.
            ->assertJsonMissingPath('data.0.wishlist.share_token');
    }
}
