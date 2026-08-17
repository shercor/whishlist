<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wishlist_is_born_private(): void
    {
        $wishlist = Wishlist::factory()->create();

        $this->assertSame(WishlistVisibility::PRIVATE, $wishlist->visibilityEnum());
        $this->assertNull($wishlist->share_token);
    }

    public function test_only_link_wishlists_get_a_share_token(): void
    {
        $porEnlace = Wishlist::factory()->visibility(WishlistVisibility::LINK)->create();
        $publica = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create();

        $this->assertNotNull($porEnlace->share_token);
        $this->assertNull($publica->share_token);
    }

    public function test_the_browsable_scope_returns_public_wishlists_and_the_users_own(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $publicaDeBruno = Wishlist::factory()->for($bruno)->visibility(WishlistVisibility::PUBLIC)->create();
        $privadaDeAna = Wishlist::factory()->for($ana)->create();
        $privadaDeBruno = Wishlist::factory()->for($bruno)->create();
        $porEnlaceDeBruno = Wishlist::factory()->for($bruno)->visibility(WishlistVisibility::LINK)->create();

        $alcanzables = Wishlist::browsableBy($ana)->pluck('id');

        $this->assertTrue($alcanzables->contains($publicaDeBruno->id));
        $this->assertTrue($alcanzables->contains($privadaDeAna->id));
        $this->assertFalse($alcanzables->contains($privadaDeBruno->id));
        // Las de enlace no se navegan: se llega con el token, no buscando.
        $this->assertFalse($alcanzables->contains($porEnlaceDeBruno->id));
    }

    public function test_deleting_the_owner_takes_their_wishlists_with_them(): void
    {
        $ana = User::factory()->create();
        $wishlist = Wishlist::factory()->for($ana)->create();

        $ana->delete();

        $this->assertNull(Wishlist::withTrashed()->find($wishlist->id));
    }

    public function test_only_an_approved_request_grants_access(): void
    {
        $wishlist = Wishlist::factory()->create();

        $aprobada = WishlistAccess::factory()->for($wishlist)
            ->status(AccessRequestStatus::APPROVED)->create();
        $pendiente = WishlistAccess::factory()->for($wishlist)
            ->status(AccessRequestStatus::PENDING)->create();
        WishlistAccess::factory()->for($wishlist)
            ->status(AccessRequestStatus::REJECTED)->create();
        WishlistAccess::factory()->for($wishlist)
            ->status(AccessRequestStatus::REVOKED)->create();

        $this->assertSame([$aprobada->id], $wishlist->accesses()->approved()->pluck('id')->all());
        $this->assertSame([$pendiente->id], $wishlist->accesses()->pending()->pluck('id')->all());

        $this->assertTrue($aprobada->statusEnum()->grantsAccess());
        $this->assertNull($pendiente->responded_at);
        $this->assertNotNull($aprobada->responded_at);
    }

    public function test_a_person_can_only_have_one_request_per_wishlist(): void
    {
        $wishlist = Wishlist::factory()->create();
        $camila = User::factory()->create();

        WishlistAccess::factory()->for($wishlist)->for($camila)->create();

        try {
            WishlistAccess::factory()->for($wishlist)->for($camila)->create();
            $this->fail('La base aceptó dos solicitudes de la misma persona para la misma lista.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
    }
}
