<?php

namespace Tests\Feature\Api;

use App\Enums\AccessRequestStatus;
use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El enlace secreto desde la app, que no tiene sesión: abrirlo deja anotado el
 * acceso, y ese acceso es el que abre la lista después.
 */
class ApiSharedLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $duenio;

    private Wishlist $lista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duenio = User::factory()->create(['is_private' => true]);
        $this->lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $this->duenio->id, 'share_token' => str_repeat('a', 32)]);
    }

    public function test_the_link_opens_a_private_list_to_someone_who_does_not_follow_the_owner(): void
    {
        $tia = User::factory()->create();
        Sanctum::actingAs($tia);

        $this->getJson(route('api.v1.wishlists.show', $this->lista))->assertForbidden();

        $this->postJson(route('api.v1.shared.open', ['token' => $this->lista->share_token]))
            ->assertOk()
            ->assertJsonPath('data.id', $this->lista->id)
            ->assertJsonPath('data.is_mine', false)
            ->assertJsonPath('data.owner.username', $this->duenio->username)
            // El enlace no viaja de vuelta a quien no es el dueño.
            ->assertJsonMissingPath('data.share_token');

        $this->assertDatabaseHas('wishlist_accesses', [
            'wishlist_id' => $this->lista->id,
            'user_id' => $tia->id,
            'status' => 'aprobado',
            'source' => 'enlace',
        ]);
        $this->getJson(route('api.v1.wishlists.show', $this->lista))->assertOk();
    }

    public function test_a_wrong_token_opens_nothing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.shared.open', ['token' => str_repeat('b', 32)]))->assertNotFound();
    }

    public function test_the_owner_opening_their_own_link_records_nothing(): void
    {
        Sanctum::actingAs($this->duenio);

        $this->postJson(route('api.v1.shared.open', ['token' => $this->lista->share_token]))
            ->assertOk()
            ->assertJsonPath('data.is_mine', true);

        $this->assertSame(0, $this->lista->accesses()->count());
    }

    public function test_the_link_keeps_an_invitation_and_opens_a_rejected_request(): void
    {
        $invitado = User::factory()->create();
        $rechazado = User::factory()->create();
        WishlistAccess::factory()->status(AccessRequestStatus::APPROVED)
            ->create(['wishlist_id' => $this->lista->id, 'user_id' => $invitado->id, 'source' => 'invitacion']);
        WishlistAccess::factory()->status(AccessRequestStatus::REJECTED)
            ->create(['wishlist_id' => $this->lista->id, 'user_id' => $rechazado->id, 'source' => 'solicitud']);

        foreach ([$invitado, $rechazado] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->postJson(route('api.v1.shared.open', ['token' => $this->lista->share_token]))->assertOk();
        }

        $this->assertSame('invitacion', $this->lista->accesses()->where('user_id', $invitado->id)->value('source'));
        $this->assertDatabaseHas('wishlist_accesses', [
            'user_id' => $rechazado->id,
            'status' => 'aprobado',
            'source' => 'enlace',
        ]);
        $this->assertSame(2, $this->lista->accesses()->count());
    }
}
