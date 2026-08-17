<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las reglas de quién ve la lista de quién.
 *
 * Es la parte del proyecto donde un error no se nota: nadie reclama porque le
 * muestren de más. Por eso cada camino de la policy tiene su test, y el que
 * más importa es el de dejar de seguir, porque es una regla que se sostiene
 * sola en una pregunta hecha en el momento de mirar.
 */
class FollowAccessTest extends TestCase
{
    use RefreshDatabase;

    private function seguir(User $follower, User $followed): Follow
    {
        return Follow::factory()->between($follower, $followed)->accepted()->create();
    }

    private function lista(User $duenio, WishlistVisibility $visibilidad): Wishlist
    {
        return Wishlist::factory()->visibility($visibilidad)->create(['user_id' => $duenio->id]);
    }

    public function test_a_private_profile_hides_even_its_public_wishlists(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $extranio = User::factory()->create();

        $publica = $this->lista($duenio, WishlistVisibility::PUBLIC);

        // Aunque la lista diga «pública», el perfil manda: si no fuera así,
        // marcar el perfil como privado no serviría de nada.
        $this->assertFalse($extranio->can('view', $publica));
    }

    public function test_a_public_profile_shows_its_public_wishlists_to_anyone(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $extranio = User::factory()->create();

        $this->assertTrue($extranio->can('view', $this->lista($duenio, WishlistVisibility::PUBLIC)));
    }

    public function test_a_pending_follow_request_opens_nothing(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $curioso = User::factory()->create();

        // Pendiente, no aceptada.
        Follow::factory()->between($curioso, $duenio)->create();

        $this->assertFalse($curioso->can('view', $this->lista($duenio, WishlistVisibility::PUBLIC)));
    }

    public function test_following_opens_the_public_wishlists_but_not_the_private_ones(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();

        $this->seguir($seguidor, $duenio);

        $this->assertTrue($seguidor->can('view', $this->lista($duenio, WishlistVisibility::PUBLIC)));
        // Seguir no es entrar a todo: la privada se reparte una por una.
        $this->assertFalse($seguidor->can('view', $this->lista($duenio, WishlistVisibility::PRIVATE)));
    }

    public function test_an_invitation_opens_a_private_wishlist(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();
        $this->seguir($seguidor, $duenio);

        $privada = $this->lista($duenio, WishlistVisibility::PRIVATE);
        $privada->accesses()->create([
            'user_id' => $seguidor->id,
            'status' => AccessRequestStatus::APPROVED->label(),
            'source' => AccessSource::INVITATION->label(),
            'responded_at' => now(),
        ]);

        $this->assertTrue($seguidor->can('view', $privada));
    }

    /**
     * El test que más importa de este archivo.
     *
     * El acceso invitado no se guarda como «tiene permiso para siempre»: se
     * recalcula preguntando por el seguimiento cada vez. Por eso dejar de
     * seguir corta en el acto y no hace falta ninguna tarea que limpie
     * accesos. Si alguien cambiara la policy para confiar solo en la fila
     * aprobada, este test es el que se cae.
     */
    public function test_unfollowing_closes_a_private_wishlist_immediately(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $seguidor = User::factory()->create();
        $follow = $this->seguir($seguidor, $duenio);

        $privada = $this->lista($duenio, WishlistVisibility::PRIVATE);
        $acceso = $privada->accesses()->create([
            'user_id' => $seguidor->id,
            'status' => AccessRequestStatus::APPROVED->label(),
            'source' => AccessSource::INVITATION->label(),
            'responded_at' => now(),
        ]);

        $this->assertTrue($seguidor->can('view', $privada));

        $follow->delete();
        $duenio->refresh();
        $privada->refresh();

        $this->assertFalse($seguidor->can('view', $privada));

        // Y la invitación sigue ahí: no se borró nada, solo dejó de valer.
        $this->assertDatabaseHas('wishlist_accesses', [
            'id' => $acceso->id,
            'status' => AccessRequestStatus::APPROVED->label(),
        ]);
    }

    /**
     * El acceso por enlace no depende de seguir a nadie: es la puerta para la
     * tía que no usa la app. Por eso sobrevive a que no haya seguimiento.
     */
    public function test_link_access_does_not_require_following(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $tia = User::factory()->create();

        $privada = $this->lista($duenio, WishlistVisibility::PRIVATE);
        $privada->accesses()->create([
            'user_id' => $tia->id,
            'status' => AccessRequestStatus::APPROVED->label(),
            'source' => AccessSource::LINK->label(),
            'responded_at' => now(),
        ]);

        $this->assertFalse($duenio->isFollowedBy($tia));
        $this->assertTrue($tia->can('view', $privada));
    }

    /**
     * Una lista privada se reparte de las dos formas y no hay que elegir: se
     * invita a gente *y* se pasa el enlace. Por eso siempre lleva token.
     */
    public function test_a_private_wishlist_always_has_a_link_to_share(): void
    {
        $duenio = User::factory()->create();

        $this->assertNotNull($this->lista($duenio, WishlistVisibility::PRIVATE)->share_token);
        $this->assertNull($this->lista($duenio, WishlistVisibility::PUBLIC)->share_token);
    }

    public function test_you_cannot_ask_for_a_list_of_someone_you_do_not_follow(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $extranio = User::factory()->create();
        $privada = $this->lista($duenio, WishlistVisibility::PRIVATE);

        $this->assertFalse($extranio->can('requestAccess', $privada));

        $this->seguir($extranio, $duenio);
        $duenio->refresh();

        $this->assertTrue($extranio->can('requestAccess', $privada));
    }

    public function test_the_owner_always_sees_their_own_lists(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);

        foreach (WishlistVisibility::cases() as $visibilidad) {
            $this->assertTrue($duenio->can('view', $this->lista($duenio, $visibilidad)));
        }
    }

    /**
     * La regla de privacidad del nombre: buscar el nombre real no encuentra a
     * nadie. Si esto se rompiera, el interruptor de «mostrar mi nombre» sería
     * decorativo, porque bastaría con probar nombres.
     */
    public function test_people_are_never_found_by_their_real_name(): void
    {
        User::factory()->create(['name' => 'Ana Rojas', 'username' => 'pelusa88']);

        $this->assertTrue(User::searchByUsername('pelusa')->exists());
        $this->assertFalse(User::searchByUsername('Ana Rojas')->exists());
        $this->assertFalse(User::searchByUsername('Ana')->exists());
        // Sin término no se lista a nadie: el directorio no se recorre.
        $this->assertFalse(User::searchByUsername('')->exists());
    }
}
