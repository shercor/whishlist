<?php

namespace Tests\Feature;

use App\Enums\ItemPriority;
use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_available_scope_hides_reserved_and_received_items(): void
    {
        $wishlist = Wishlist::factory()->create();

        $libre = WishlistItem::factory()->for($wishlist)->create();
        $reservado = WishlistItem::factory()->for($wishlist)->create();
        $recibido = WishlistItem::factory()->for($wishlist)->received()->create();

        Reservation::factory()->for($reservado, 'wishlistItem')->create();

        $disponibles = $wishlist->items()->available()->pluck('id');

        $this->assertTrue($disponibles->contains($libre->id));
        $this->assertFalse($disponibles->contains($reservado->id));
        $this->assertFalse($disponibles->contains($recibido->id));
    }

    public function test_an_item_whose_reservation_was_released_is_available_again(): void
    {
        $wishlist = Wishlist::factory()->create();
        $item = WishlistItem::factory()->for($wishlist)->create();

        // Reserva histórica: ya no bloquea.
        Reservation::factory()->for($item, 'wishlistItem')->released()->create();

        $this->assertTrue($wishlist->items()->available()->pluck('id')->contains($item->id));
    }

    public function test_the_received_scope_only_returns_gifts_already_delivered(): void
    {
        $wishlist = Wishlist::factory()->create();
        WishlistItem::factory()->count(2)->for($wishlist)->create();
        $recibido = WishlistItem::factory()->for($wishlist)->received()->create();

        $recibidos = $wishlist->items()->received()->get();

        $this->assertCount(1, $recibidos);
        $this->assertTrue($recibidos->first()->is($recibido));
        $this->assertTrue($recibido->isReceived());
    }

    public function test_the_ordered_scope_sorts_by_position_and_then_by_priority(): void
    {
        $wishlist = Wishlist::factory()->create();

        // Misma posición para los tres: así el desempate lo tiene que resolver
        // el FIELD() de la prioridad.
        $baja = WishlistItem::factory()->for($wishlist)->at(1)->priority(ItemPriority::LOW)->create();
        $alta = WishlistItem::factory()->for($wishlist)->at(1)->priority(ItemPriority::HIGH)->create();
        $media = WishlistItem::factory()->for($wishlist)->at(1)->priority(ItemPriority::MEDIUM)->create();

        // Este va primero por posición aunque su prioridad sea la más baja.
        $primero = WishlistItem::factory()->for($wishlist)->at(0)->priority(ItemPriority::LOW)->create();

        $this->assertSame(
            [$primero->id, $alta->id, $media->id, $baja->id],
            $wishlist->items()->ordered()->pluck('id')->all()
        );
    }

    public function test_the_alias_takes_precedence_over_the_catalog_name(): void
    {
        $product = Product::factory()->create(['name' => 'Peluche Pikachu 30 cm']);

        $sinAlias = WishlistItem::factory()->for($product)->create(['alias' => null]);
        $conAlias = WishlistItem::factory()->for($product)->create(['alias' => 'El Pikachu grande']);

        $this->assertSame('Peluche Pikachu 30 cm', $sinAlias->displayName());
        $this->assertSame('El Pikachu grande', $conAlias->displayName());
    }

    public function test_is_reserved_for_viewer_says_if_it_is_taken_but_not_by_whom(): void
    {
        $item = WishlistItem::factory()->create();

        $this->assertFalse($item->isReservedForViewer());

        $reservation = Reservation::factory()->for($item, 'wishlistItem')->create();
        $this->assertTrue($item->isReservedForViewer());

        $reservation->release(ReservationStatus::CANCELLED);
        $this->assertFalse($item->isReservedForViewer());
    }

    public function test_the_same_product_can_be_added_twice_to_ask_for_two_units(): void
    {
        $wishlist = Wishlist::factory()->create();
        $product = Product::factory()->create();

        // Un ítem equivale a una unidad: repetir el producto es cómo se piden
        // tres tazas. Por eso wishlist_items no tiene único (wishlist, product).
        WishlistItem::factory()->count(3)->for($wishlist)->for($product)->create();

        $this->assertSame(3, $wishlist->items()->where('product_id', $product->id)->count());
    }
}
