<?php

namespace Tests\Feature\Api;

use App\Enums\AccessRequestStatus;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lo que necesita la app para las listas privadas, Mi gente y el menú.
 */
class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Wishlist}
     */
    private function duenioSeguidorYLista(): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $seguidor = User::factory()->create();
        Follow::factory()->between($seguidor, $duenio)->accepted()->create();
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id, 'name' => 'Lista secreta']);

        return [$duenio, $seguidor, $lista];
    }

    public function test_a_locked_list_says_whose_it_is_but_not_its_name(): void
    {
        [$duenio, $seguidor, $lista] = $this->duenioSeguidorYLista();

        Sanctum::actingAs($seguidor);

        $respuesta = $this->getJson(route('api.v1.wishlists.show', $lista))
            ->assertForbidden()
            ->assertJsonPath('owner.username', $duenio->username)
            ->assertJsonPath('access_status', null)
            ->assertJsonPath('can_request', true);

        $this->assertStringNotContainsString('Lista secreta', $respuesta->content());

        $this->postJson(route('api.v1.access.store', $lista))->assertCreated();

        $this->getJson(route('api.v1.wishlists.show', $lista))
            ->assertForbidden()
            ->assertJsonPath('access_status', 'pendiente')
            ->assertJsonPath('can_request', false);
    }

    public function test_my_requests_only_name_the_lists_i_was_given(): void
    {
        [$duenio, $seguidor, $lista] = $this->duenioSeguidorYLista();
        $otra = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id, 'name' => 'Lista abierta para mí']);
        WishlistAccess::factory()->create(['wishlist_id' => $lista->id, 'user_id' => $seguidor->id]);
        WishlistAccess::factory()->status(AccessRequestStatus::APPROVED)
            ->create(['wishlist_id' => $otra->id, 'user_id' => $seguidor->id]);

        Sanctum::actingAs($seguidor);

        $respuesta = $this->getJson(route('api.v1.access.index'))->assertOk();
        $enviadas = collect($respuesta->json('data.sent'))->keyBy('wishlist.id');

        $this->assertSame($duenio->username, $enviadas[$lista->id]['wishlist']['owner']['username']);
        $this->assertArrayNotHasKey('name', $enviadas[$lista->id]['wishlist']);
        $this->assertSame('Lista abierta para mí', $enviadas[$otra->id]['wishlist']['name']);
        $this->assertStringNotContainsString('Lista secreta', $respuesta->content());
    }

    public function test_a_list_is_given_by_username_only_to_a_follower(): void
    {
        [$duenio, $seguidor, $lista] = $this->duenioSeguidorYLista();
        $extranio = User::factory()->create();

        Sanctum::actingAs($duenio);

        $this->postJson(route('api.v1.access.invite', $lista), ['username' => $extranio->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->postJson(route('api.v1.access.invite', $lista), ['username' => $seguidor->username])
            ->assertCreated()
            ->assertJsonPath('data.status', 'aprobado');

        $this->assertTrue($seguidor->can('view', $lista->fresh()));
    }

    public function test_my_profile_counts_what_is_waiting_for_me(): void
    {
        [$duenio, $seguidor, $lista] = $this->duenioSeguidorYLista();
        WishlistAccess::factory()->create(['wishlist_id' => $lista->id, 'user_id' => $seguidor->id]);
        Follow::factory()->between(User::factory()->create(), $duenio)->create();

        Sanctum::actingAs($duenio);

        $this->getJson(route('api.v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.name', $duenio->name)
            ->assertJsonPath('meta.pending_requests', 2)
            ->assertJsonPath('meta.unread_notifications', 0);
    }
}
