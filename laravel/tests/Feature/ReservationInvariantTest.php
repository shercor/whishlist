<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El invariante que sostiene todo el dominio: un regalo lo reserva una sola
 * persona a la vez, y lo garantiza la base de datos, no la aplicación. Estos
 * tests existen para que nadie pueda quitar el índice único sin enterarse.
 */
class ReservationInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_rejects_a_second_active_reservation_on_the_same_item(): void
    {
        $item = WishlistItem::factory()->create();

        Reservation::factory()->for($item, 'wishlistItem')->create();

        try {
            Reservation::factory()->for($item, 'wishlistItem')->create();
            $this->fail('La base aceptó dos reservas activas sobre el mismo ítem.');
        } catch (QueryException $e) {
            // 23000 es la violación de restricción de integridad. Si esto
            // cambia, es que el índice reservations_one_active_per_item ya no
            // está haciendo su trabajo.
            $this->assertSame('23000', $e->getCode());
        }

        $this->assertSame(1, $item->reservations()->count());
    }

    public function test_releasing_a_reservation_lets_someone_else_reserve_the_item(): void
    {
        $item = WishlistItem::factory()->create();
        $reservation = Reservation::factory()->for($item, 'wishlistItem')->create();

        $reservation->release(ReservationStatus::CANCELLED);

        $nueva = Reservation::factory()->for($item, 'wishlistItem')->create();

        $this->assertNull($reservation->fresh()->active_flag);
        $this->assertNotNull($reservation->fresh()->released_at);
        $this->assertSame(ReservationStatus::CANCELLED->label(), $reservation->fresh()->status);
        $this->assertTrue($nueva->isActive());
        $this->assertSame(2, $item->reservations()->count());
    }

    public function test_many_finished_reservations_of_the_same_item_can_coexist(): void
    {
        $item = WishlistItem::factory()->create();

        // Tres personas que reservaron y soltaron, más la que lo tiene ahora.
        Reservation::factory()->count(3)->for($item, 'wishlistItem')->released()->create();
        Reservation::factory()->for($item, 'wishlistItem')->create();

        $this->assertSame(4, $item->reservations()->count());
        $this->assertSame(1, $item->reservations()->whereNotNull('active_flag')->count());
    }

    public function test_the_active_scope_only_returns_live_reservations(): void
    {
        Reservation::factory()->count(2)->create();
        Reservation::factory()->count(3)->released()->create();
        Reservation::factory()->fulfilled()->create();

        $this->assertSame(2, Reservation::active()->count());
        $this->assertSame(6, Reservation::count());
    }

    public function test_the_expired_scope_only_returns_live_reservations_past_their_deadline(): void
    {
        $vencida = Reservation::factory()->expired()->create();

        // Vigente: no vencida todavía.
        Reservation::factory()->create();
        // Sin plazo: no puede vencer.
        Reservation::factory()->neverExpires()->create();
        // Vencida pero ya soltada: el job no tiene nada que hacer con ella.
        Reservation::factory()->expired()->released()->create();

        $expiradas = Reservation::expired()->get();

        $this->assertCount(1, $expiradas);
        $this->assertTrue($expiradas->first()->is($vencida));
    }

    public function test_release_frees_the_item_for_the_available_scope(): void
    {
        $item = WishlistItem::factory()->create();
        $reservation = Reservation::factory()->for($item, 'wishlistItem')->create();

        $this->assertSame(0, WishlistItem::available()->whereKey($item->id)->count());

        $reservation->release(ReservationStatus::EXPIRED);

        $this->assertSame(1, WishlistItem::available()->whereKey($item->id)->count());
    }

    public function test_a_reservation_belongs_to_the_person_who_made_it(): void
    {
        $bruno = User::factory()->create();
        $reservation = Reservation::factory()->for($bruno)->create();

        $this->assertTrue($reservation->user->is($bruno));
        $this->assertSame(ReservationStatus::ACTIVE, $reservation->statusEnum());
    }
}
