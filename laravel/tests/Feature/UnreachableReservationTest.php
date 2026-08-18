<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\AccessSource;
use App\Enums\WishlistVisibility;
use App\Models\Follow;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use App\Notifications\ReservationReleased;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Las reservas que quedan fuera de alcance.
 *
 * Era la última decisión abierta de la sección 6 del HANDOFF: si reservabas un
 * regalo y después perdías el acceso a la lista, la reserva seguía viva y el
 * regalo bloqueado catorce días para todos los demás, sin que nadie pudiera
 * hacer nada —el dueño no puede enterarse, y tú ya no ves la lista—.
 *
 * Los tests van por casos de **cómo** se pierde el acceso, y a propósito
 * incluyen los dos que no tocan ninguna fila de seguimiento ni de acceso: son
 * los que un arreglo enganchado a los controladores se habría dejado fuera.
 */
class UnreachableReservationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Alguien que sigue al dueño y tiene reservado un regalo de su lista.
     *
     * @return array{0: User, 1: User, 2: Wishlist, 3: Reservation}
     */
    private function conAcceso(WishlistVisibility $visibilidad = WishlistVisibility::PUBLIC): array
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $regalador = User::factory()->create();

        Follow::factory()->between($regalador, $duenio)->accepted()->create();

        $lista = Wishlist::factory()->visibility($visibilidad)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        // Seguir abre las listas públicas, no las privadas: esas se reparten
        // una por una. Si la lista es privada hace falta la invitación.
        if ($visibilidad === WishlistVisibility::PRIVATE) {
            WishlistAccess::factory()
                ->status(AccessRequestStatus::APPROVED)
                ->create([
                    'wishlist_id' => $lista->id,
                    'user_id' => $regalador->id,
                    'source' => AccessSource::INVITATION->label(),
                ]);
        }

        $reserva = Reservation::factory()
            ->for($item, 'wishlistItem')
            ->for($regalador)
            ->create();

        // Punto de partida: puede verla y la tiene reservada.
        $this->assertTrue($regalador->can('view', $lista));

        return [$duenio, $regalador, $lista, $reserva];
    }

    private function barrer(): int
    {
        return app(ReservationService::class)->releaseUnreachable();
    }

    public function test_a_reservation_you_can_still_reach_is_left_alone(): void
    {
        [, , , $reserva] = $this->conAcceso();

        $this->assertSame(0, $this->barrer());
        $this->assertTrue($reserva->fresh()->isActive());
    }

    public function test_unfollowing_frees_the_gift_you_had_taken(): void
    {
        [$duenio, $regalador, , $reserva] = $this->conAcceso();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    public function test_being_removed_as_a_follower_frees_it_too(): void
    {
        [$duenio, $regalador, , $reserva] = $this->conAcceso();

        $this->actingAs($duenio)->delete(route('follows.remove', $regalador));

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    public function test_revoking_an_access_to_a_private_list_frees_it(): void
    {
        [$duenio, $regalador, $lista, $reserva] = $this->conAcceso(WishlistVisibility::PRIVATE);

        $acceso = $lista->accesses()->where('user_id', $regalador->id)->firstOrFail();

        $this->actingAs($duenio)->delete(route('access.revoke', [$lista, $acceso]));

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * **El caso que un arreglo enganchado a los controladores se pierde.** El
     * dueño vuelve privada una lista que era pública: no se borra ninguna fila
     * de seguimiento ni de acceso, y sin embargo quien reservó ya no la alcanza.
     */
    public function test_turning_a_public_list_private_frees_the_reservations(): void
    {
        [$duenio, , $lista, $reserva] = $this->conAcceso();

        $this->actingAs($duenio)->put(route('wishlists.update', $lista), [
            'name' => $lista->name,
            'visibility' => WishlistVisibility::PRIVATE->label(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * El otro que no toca ninguna fila: el dueño cierra su perfil, y sus listas
     * públicas dejan de verse para quien no lo sigue.
     */
    public function test_closing_your_profile_frees_reservations_of_non_followers(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        // Reservó sin seguir a nadie: la lista era pública y el perfil abierto.
        $regalador = User::factory()->create();
        $reserva = Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->assertTrue($regalador->can('view', $lista));

        $duenio->update(['is_private' => true]);

        $this->assertSame(1, $this->barrer());
        $this->assertFalse($reserva->fresh()->isActive());
    }

    /**
     * El regalo vuelve a estar disponible, que es todo el punto: mientras la
     * reserva viviera, nadie más podía comprarlo.
     */
    public function test_the_gift_can_be_taken_by_someone_else_afterwards(): void
    {
        [$duenio, $regalador, $lista] = $this->conAcceso();

        $item = $lista->items()->firstOrFail();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));
        $this->barrer();

        $otro = User::factory()->create();
        Follow::factory()->between($otro, $duenio)->accepted()->create();

        $this->assertTrue($otro->can('create', [Reservation::class, $item->fresh()]));
        $this->assertSame(1, WishlistItem::available()->whereKey($item->id)->count());
    }

    /**
     * Se le avisa a quien la tenía. Soltarla en silencio sería peor que
     * dejarla: iría a comprar un regalo que ya no tiene tomado.
     */
    public function test_the_person_who_had_it_is_told(): void
    {
        Notification::fake();

        [$duenio, $regalador] = $this->conAcceso();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));
        $this->barrer();

        Notification::assertSentTo($regalador, ReservationReleased::class);
        // Al dueño no: enterarse de que había una reserva es justo la sorpresa
        // que la aplicación protege.
        Notification::assertNotSentTo($duenio, ReservationReleased::class);
    }

    /**
     * El dueño no puede deducir nada mirando: su lista no cambia.
     */
    public function test_the_owner_sees_nothing_of_this(): void
    {
        [$duenio, $regalador] = $this->conAcceso();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));
        $this->barrer();

        $html = $this->actingAs($duenio)
            ->get(route('wishlists.index'))
            ->assertOk()
            ->getContent();

        // Nada de buscar la palabra «reserva» a secas: sale en la URL de «Voy a
        // regalar» de la barra, que está en todas las páginas. Lo que no puede
        // aparecer es quién reservó ni rastro de que algo se soltó.
        foreach ([$regalador->username, $regalador->name, 'Se soltó'] as $rastro) {
            $this->assertStringNotContainsString($rastro, $html);
        }
    }

    /**
     * Correrlo dos veces no suelta nada de más ni avisa dos veces: la segunda
     * pasada ya no ve reservas vivas fuera de alcance.
     */
    public function test_running_the_sweep_twice_changes_nothing_more(): void
    {
        [$duenio, $regalador] = $this->conAcceso();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));

        $this->assertSame(1, $this->barrer());
        $this->assertSame(0, $this->barrer());
        $this->assertSame(1, $regalador->notifications()->count());
    }

    public function test_the_command_reports_what_it_did(): void
    {
        [$duenio, $regalador] = $this->conAcceso();

        $this->artisan('reservations:release-unreachable')
            ->expectsOutputToContain('No había reservas fuera de alcance.')
            ->assertSuccessful();

        $this->actingAs($regalador)->delete(route('follows.destroy', $duenio));

        $this->artisan('reservations:release-unreachable')
            ->expectsOutputToContain('Reservas soltadas: 1.')
            ->assertSuccessful();
    }

    /**
     * Quien llegó por el enlace secreto no depende de seguir a nadie, así que
     * dejar de seguir no puede quitarle la reserva.
     */
    public function test_a_link_access_survives_unfollowing(): void
    {
        $duenio = User::factory()->create(['is_private' => true]);
        $regalador = User::factory()->create();

        $lista = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);
        $item = WishlistItem::factory()->for($lista)->create();

        WishlistAccess::factory()
            ->status(AccessRequestStatus::APPROVED)
            ->create([
                'wishlist_id' => $lista->id,
                'user_id' => $regalador->id,
                'source' => AccessSource::LINK->label(),
            ]);

        $reserva = Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->assertSame(0, $this->barrer());
        $this->assertTrue($reserva->fresh()->isActive());
    }
}
