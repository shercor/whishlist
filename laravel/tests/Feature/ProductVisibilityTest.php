<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_scope_only_returns_the_curated_catalog(): void
    {
        $ana = User::factory()->create();

        $catalogo = Product::factory()->count(2)->create();
        Product::factory()->privateFor($ana)->create();

        $this->assertEqualsCanonicalizing(
            $catalogo->pluck('id')->all(),
            Product::public()->pluck('id')->all()
        );
    }

    public function test_a_user_sees_the_catalog_plus_their_own_private_products(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $catalogo = Product::factory()->create();
        $taza = Product::factory()->privateFor($ana)->create();
        $privadoDeBruno = Product::factory()->privateFor($bruno)->create();

        $visibles = Product::visibleTo($ana)->pluck('id');

        $this->assertTrue($visibles->contains($catalogo->id));
        $this->assertTrue($visibles->contains($taza->id));
        $this->assertFalse($visibles->contains($privadoDeBruno->id));
    }

    public function test_a_private_product_is_born_belonging_to_its_author(): void
    {
        $ana = User::factory()->create();
        $taza = Product::factory()->privateFor($ana)->create();

        $this->assertFalse($taza->is_public);
        $this->assertTrue($taza->creator->is($ana));
    }

    public function test_deleting_the_author_keeps_the_product_in_the_catalog(): void
    {
        $ana = User::factory()->create();
        $producto = Product::factory()->create(['created_by_user_id' => $ana->id]);

        $ana->delete();

        // nullOnDelete: el catálogo no debe perder productos porque alguien se
        // dé de baja.
        $this->assertNull($producto->fresh()->created_by_user_id);
        $this->assertNotNull($producto->fresh());
    }
}
