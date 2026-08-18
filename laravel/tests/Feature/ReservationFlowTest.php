<?php

namespace Tests\Feature;

use App\Enums\WishlistVisibility;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reservar por HTTP: las dos cosas que la base de datos no puede decidir sola.
 *
 * Que el dueño no reserve en su propia lista lo sostiene únicamente la policy
 * —`reservations` no sabe de listas, así que ningún índice puede impedirlo—, y
 * que perder la carrera termine en un aviso y no en un error 500 lo sostiene
 * únicamente el controlador. Ninguna de las dos se cae sola si se rompe.
 */
class ReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un regalo libre en una lista pública de otra persona: el caso en que
     * reservar está permitido. Los tests parten de acá y cambian una cosa.
     *
     * El dueño nace con el perfil abierto a propósito. Un usuario es privado
     * por defecto, y sobre un perfil privado hasta la lista pública queda
     * fuera del alcance de quien no lo sigue: sin esto, todos los 403 de acá
     * abajo saldrían por la razón equivocada.
     */
    private function regaloDeOtro(?User $duenio = null): WishlistItem
    {
        $duenio ??= User::factory()->create(['is_private' => false]);

        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id]);

        return WishlistItem::factory()->for($lista)->create();
    }

    public function test_the_owner_cannot_reserve_a_gift_from_their_own_wishlist(): void
    {
        $item = $this->regaloDeOtro();
        $duenio = $item->wishlist->user;

        $this->assertFalse($duenio->can('create', [Reservation::class, $item]));

        $this->actingAs($duenio)
            ->post(route('reservations.store', $item))
            ->assertForbidden();

        // Y sobre todo: no quedó nada guardado. Una reserva del dueño sobre su
        // propia lista bloquearía el regalo para quien sí iba a comprarlo.
        $this->assertSame(0, $item->reservations()->count());
    }

    public function test_someone_else_can_reserve_it_and_gets_told_the_owner_will_not_find_out(): void
    {
        $regalador = User::factory()->create();
        $item = $this->regaloDeOtro();

        $this->actingAs($regalador)
            ->from(route('gifts.show', $item->wishlist))
            ->post(route('reservations.store', $item), ['note' => 'Se lo llevo el sábado'])
            ->assertRedirect(route('gifts.show', $item->wishlist))
            ->assertSessionHas('status');

        $reserva = $item->reservations()->firstOrFail();

        $this->assertSame($regalador->id, $reserva->user_id);
        $this->assertTrue($reserva->isActive());
        $this->assertSame('Se lo llevo el sábado', $reserva->note);
    }

    /**
     * Cuando ya hay una reserva viva, la policy corta antes de llegar a la
     * base: el segundo no ve el botón y, si lo fuerza, recibe un 403.
     */
    public function test_a_gift_that_is_already_taken_cannot_be_reserved_again(): void
    {
        $item = $this->regaloDeOtro();

        Reservation::factory()->for($item, 'wishlistItem')->create();

        $tardio = User::factory()->create();

        // Alcanza la lista de sobra: lo único que lo detiene es que el regalo
        // ya está tomado.
        $this->assertTrue($tardio->can('view', $item->wishlist));

        $this->actingAs($tardio)
            ->post(route('reservations.store', $item))
            ->assertForbidden();

        $this->assertSame(1, $item->reservations()->count());
    }

    /**
     * La carrera de verdad: dos personas aprietan a la vez, las dos pasan la
     * policy porque en ese instante el regalo estaba libre, y quien decide es
     * el índice único. El servicio traduce ese choque a null en vez de dejar
     * salir la excepción.
     */
    public function test_the_service_returns_null_instead_of_throwing_when_someone_else_won(): void
    {
        $item = WishlistItem::factory()->create();
        $ganador = User::factory()->create();
        $perdedor = User::factory()->create();

        $reservations = app(ReservationService::class);

        $this->assertNotNull($reservations->reserve($item, $ganador));
        $this->assertNull($reservations->reserve($item, $perdedor));

        $this->assertSame(1, $item->reservations()->whereNotNull('active_flag')->count());
    }

    /**
     * Y el controlador convierte ese null en un aviso. Se simula el choque con
     * un doble del servicio porque por HTTP no se puede: la policy vería el
     * regalo ya tomado y cortaría antes, que es justo el camino que este test
     * no quiere probar.
     */
    public function test_losing_the_race_shows_a_message_instead_of_blowing_up(): void
    {
        $perdedor = User::factory()->create();
        $item = $this->regaloDeOtro();

        $this->mock(ReservationService::class)
            ->shouldReceive('reserve')
            ->once()
            ->andReturnNull();

        $this->actingAs($perdedor)
            ->from(route('gifts.show', $item->wishlist))
            ->post(route('reservations.store', $item))
            ->assertRedirect(route('gifts.show', $item->wishlist))
            ->assertSessionHas('error', 'Alguien se te adelantó y ya lo reservó.');
    }

    /**
     * Soltar la reserva la puede solo quien reservó, y el regalo vuelve a
     * ofrecerse. El dueño no aparece por acá: no debería ni saber que existía.
     */
    public function test_letting_go_of_your_reservation_frees_the_gift(): void
    {
        $regalador = User::factory()->create();
        $item = $this->regaloDeOtro();

        Reservation::factory()->for($item, 'wishlistItem')->for($regalador)->create();

        $this->actingAs($regalador)
            ->from(route('reservations.index'))
            ->delete(route('reservations.destroy', $item))
            ->assertSessionHas('status');

        $this->assertSame(0, $item->reservations()->whereNotNull('active_flag')->count());
        $this->assertSame(1, WishlistItem::available()->whereKey($item->id)->count());
    }
}
