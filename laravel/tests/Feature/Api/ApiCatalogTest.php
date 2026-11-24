<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lo que la app necesita del catálogo para agregar un regalo: las categorías
 * para escribirlo a mano, y saber qué fichas publicó uno mismo.
 */
class ApiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_categories_are_the_active_ones_by_name(): void
    {
        Category::factory()->create(['name' => 'Tecnología']);
        Category::factory()->create(['name' => 'Libros']);
        Category::factory()->create(['name' => 'Descontinuada', 'active' => false]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Libros', 'Tecnología'])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_the_categories_need_a_token(): void
    {
        $this->getJson(route('api.v1.categories.index'))->assertUnauthorized();
    }

    public function test_a_product_says_whether_it_is_mine(): void
    {
        $ana = User::factory()->create();
        $mia = Product::factory()->create(['created_by_user_id' => $ana->id]);
        $deOtro = Product::factory()->create(['created_by_user_id' => User::factory()->create()->id]);
        $delCatalogo = Product::factory()->create();

        Sanctum::actingAs($ana);

        $this->getJson(route('api.v1.products.show', $mia))->assertJsonPath('data.is_mine', true);
        $this->getJson(route('api.v1.products.show', $deOtro))->assertJsonPath('data.is_mine', false);
        $this->getJson(route('api.v1.products.show', $delCatalogo))->assertJsonPath('data.is_mine', false);
    }
}
