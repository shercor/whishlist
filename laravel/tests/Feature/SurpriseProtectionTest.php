<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La sorpresa es lo que hace especial a esta aplicación: el dueño de la lista
 * no puede enterarse de quién le va a regalar qué. La estructura lo protege
 * —las reservas viven en otra tabla— y estos tests vigilan que siga así.
 */
class SurpriseProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_items_table_stores_nothing_about_who_reserved(): void
    {
        $columnas = Schema::getColumnListing('wishlist_items');

        // El modelo original traía reserved_by_user dentro del ítem. Si alguien
        // lo vuelve a agregar, cualquier select del dueño se lleva la sorpresa.
        foreach ($columnas as $columna) {
            $this->assertStringNotContainsString('reserv', $columna);
            $this->assertStringNotContainsString('user', $columna);
        }
    }

    public function test_reading_an_item_does_not_carry_reservation_data(): void
    {
        $item = WishlistItem::factory()->create();
        Reservation::factory()->for($item, 'wishlistItem')->create();

        // Así es como el dueño ve su propia lista.
        $comoLoVeElDueno = WishlistItem::query()->whereKey($item->id)->firstOrFail()->toArray();

        $this->assertArrayNotHasKey('reservations', $comoLoVeElDueno);
        $this->assertArrayNotHasKey('active_reservation', $comoLoVeElDueno);
        $this->assertArrayNotHasKey('user_id', $comoLoVeElDueno);
    }

    public function test_loading_a_whole_wishlist_never_eager_loads_reservations(): void
    {
        $wishlist = Wishlist::factory()->create();
        $item = WishlistItem::factory()->for($wishlist)->create();
        Reservation::factory()->for($item, 'wishlistItem')->create();

        // La consulta más golosa que haría una pantalla del dueño.
        $cargada = Wishlist::with('items.product')->whereKey($wishlist->id)->firstOrFail();

        $this->assertFalse($cargada->items->first()->relationLoaded('reservations'));
        $this->assertFalse($cargada->items->first()->relationLoaded('activeReservation'));

        // Y tampoco aparece al serializar toda la lista.
        $this->assertStringNotContainsString('"reservations"', json_encode($cargada));
    }

    public function test_getting_to_the_reserver_requires_asking_for_it_explicitly(): void
    {
        $bruno = User::factory()->create();
        $item = WishlistItem::factory()->create();
        Reservation::factory()->for($item, 'wishlistItem')->for($bruno)->create();

        // Nadie llega acá por accidente: hay que nombrar la relación.
        $this->assertTrue($item->activeReservation->user->is($bruno));
    }

    public function test_the_owner_can_count_what_is_available_without_learning_who_took_it(): void
    {
        $wishlist = Wishlist::factory()->create();
        $reservado = WishlistItem::factory()->for($wishlist)->create();
        WishlistItem::factory()->count(2)->for($wishlist)->create();

        Reservation::factory()->for($reservado, 'wishlistItem')->create();

        // El dueño puede saber cuántos siguen libres —eso no delata a nadie—
        // pero el resultado no trae una sola fila de reservations.
        $disponibles = $wishlist->items()->available()->get();

        $this->assertCount(2, $disponibles);
        $this->assertFalse($disponibles->first()->relationLoaded('reservations'));
    }
}
