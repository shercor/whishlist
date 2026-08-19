<?php

namespace Tests\Feature;

use App\Enums\WishlistVisibility;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Notifications\AccessRequested;
use App\Notifications\ReservationExpiring;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Las notificaciones.
 *
 * Dos avisos y una regla que manda sobre los dos: el dueño de una lista no
 * puede enterarse por acá de nada que tenga que ver con reservas. La
 * notificación de «tu reserva vence» dice qué regalo reservaste y para quién,
 * que es exactamente la sorpresa que la aplicación existe para proteger.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function listaPrivadaDe(User $duenio): Wishlist
    {
        return Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $duenio->id]);
    }

    /**
     * Una reserva viva sobre una lista que quien reservó **puede ver**.
     *
     * No es un detalle del montaje. Una reserva solo existe si su dueño pudo
     * abrir la lista para hacerla, y desde que `warnExpiring()` se calla ante
     * lo inalcanzable, montar la escena con `Reservation::factory()` a secas
     * describe algo que no puede pasar: la lista nace privada, así que quien
     * reserva no la ve, y el aviso —con razón— no sale.
     *
     * @return array{0: User, 1: User, 2: Reservation}
     */
    private function reservaAlcanzable(array $atributos = []): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $regalador = User::factory()->create();

        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id]);

        $item = WishlistItem::factory()->for($lista)->create();

        $reserva = Reservation::factory()
            ->for($item, 'wishlistItem')
            ->for($regalador)
            ->create($atributos);

        $this->assertTrue($regalador->can('view', $lista));

        return [$duenio, $regalador, $reserva];
    }

    // --- Pedir acceso a una lista -------------------------------------------

    public function test_asking_for_a_list_notifies_its_owner(): void
    {
        Notification::fake();

        $duenio = User::factory()->create(['is_private' => false]);
        $curioso = User::factory()->create();
        $lista = $this->listaPrivadaDe($duenio);

        // Pedir acceso exige seguir al dueño; con perfil público se acepta solo.
        $this->actingAs($curioso)->post(route('follows.store', $duenio));

        $this->actingAs($curioso)
            ->post(route('access.store', $lista), ['message' => '¿Me la muestras?'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($duenio, AccessRequested::class);
        Notification::assertNotSentTo($curioso, AccessRequested::class);
    }

    public function test_the_notification_never_carries_a_hidden_real_name(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $curioso = User::factory()->create(['name' => 'Ana Rojas', 'show_name' => false]);
        $lista = $this->listaPrivadaDe($duenio);

        $this->actingAs($curioso)->post(route('follows.store', $duenio));
        $this->actingAs($curioso)->post(route('access.store', $lista));

        $datos = $duenio->notifications()->firstOrFail()->data;

        $this->assertStringNotContainsString('Ana Rojas', json_encode($datos));
        $this->assertStringContainsString($curioso->handle(), $datos['titulo']);
    }

    // --- Reserva por vencer --------------------------------------------------

    /**
     * El caso que motivó todo esto: sin aviso, el job de reservas vencidas
     * suelta el regalo y quien iba a comprarlo se entera cuando ya lo tomó otro.
     */
    public function test_a_reservation_about_to_expire_warns_the_person_who_made_it(): void
    {
        Notification::fake();

        [, $regalador, $reserva] = $this->reservaAlcanzable(['expires_at' => now()->addDays(2)]);

        app(ReservationService::class)->warnExpiring();

        Notification::assertSentTo($regalador, ReservationExpiring::class);
        $this->assertNotNull($reserva->fresh()->expiry_warned_at);
    }

    /**
     * **La regla que manda.** El dueño de la lista no recibe nada: enterarse
     * de que su regalo está reservado le arruinaría la sorpresa.
     */
    public function test_the_wishlist_owner_is_never_warned_about_a_reservation(): void
    {
        Notification::fake();

        [$duenio, $regalador] = $this->reservaAlcanzable(['expires_at' => now()->addDay()]);

        app(ReservationService::class)->warnExpiring();

        Notification::assertNothingSentTo($duenio);
        Notification::assertSentTo($regalador, ReservationExpiring::class);
    }

    public function test_a_reservation_with_time_left_is_not_warned_yet(): void
    {
        Notification::fake();

        Reservation::factory()->create(['expires_at' => now()->addDays(10)]);

        app(ReservationService::class)->warnExpiring();

        Notification::assertNothingSent();
    }

    /**
     * El comando corre a diario sobre una ventana de tres días, así que sin la
     * marca `expiry_warned_at` la misma reserva avisaría tres veces.
     */
    public function test_the_same_reservation_is_never_warned_twice(): void
    {
        Notification::fake();

        $this->reservaAlcanzable(['expires_at' => now()->addDays(2)]);

        $servicio = app(ReservationService::class);

        $this->assertSame(1, $servicio->warnExpiring());
        $this->assertSame(0, $servicio->warnExpiring());

        Notification::assertCount(1);
    }

    public function test_a_released_reservation_is_not_warned(): void
    {
        Notification::fake();

        Reservation::factory()->released()->create(['expires_at' => now()->addDay()]);

        app(ReservationService::class)->warnExpiring();

        Notification::assertNothingSent();
    }

    // --- La campana ----------------------------------------------------------

    public function test_the_bell_counts_only_your_own_unread_notifications(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $ana->notify(new ReservationExpiring(
            Reservation::factory()->for($ana)->create(['expires_at' => now()->addDay()])
        ));

        $this->assertSame(1, $ana->unreadNotifications()->count());
        $this->assertSame(0, $bruno->unreadNotifications()->count());
    }

    public function test_opening_a_notification_marks_it_read_and_goes_where_it_points(): void
    {
        $regalador = User::factory()->create();
        $regalador->notify(new ReservationExpiring(
            Reservation::factory()->for($regalador)->create(['expires_at' => now()->addDay()])
        ));

        $notificacion = $regalador->notifications()->firstOrFail();

        $this->actingAs($regalador)
            ->get(route('notifications.open', $notificacion->id))
            ->assertRedirect(route('reservations.index'));

        $this->assertSame(0, $regalador->unreadNotifications()->count());
    }

    /**
     * Las notificaciones se buscan dentro de las tuyas, así que el id de otra
     * persona no es una puerta: da 404, no su contenido.
     */
    public function test_you_cannot_open_someone_elses_notification(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $ana->notify(new ReservationExpiring(
            Reservation::factory()->for($ana)->create(['expires_at' => now()->addDay()])
        ));

        $deAna = $ana->notifications()->firstOrFail();

        $this->actingAs($bruno)
            ->get(route('notifications.open', $deAna->id))
            ->assertNotFound();

        $this->assertSame(1, $ana->unreadNotifications()->count());
    }

    public function test_the_list_shows_yours_and_only_yours(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $ana->notify(new ReservationExpiring(
            Reservation::factory()->for($ana)->create(['expires_at' => now()->addDay()])
        ));

        $this->actingAs($bruno)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Nada por ahora', false);
    }

    public function test_marking_all_as_read_empties_the_bell(): void
    {
        $regalador = User::factory()->create();

        foreach (range(1, 2) as $ignorado) {
            $regalador->notify(new ReservationExpiring(
                Reservation::factory()->for($regalador)->create(['expires_at' => now()->addDay()])
            ));
        }

        $this->assertSame(2, $regalador->unreadNotifications()->count());

        $this->actingAs($regalador)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertSessionHas('status');

        $this->assertSame(0, $regalador->fresh()->unreadNotifications()->count());
    }
}
