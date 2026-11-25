<?php

namespace Tests\Feature\Api;

use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los regalos de una lista, por página.
 *
 * `show` ya no trae los regalos —una lista puede tener muchos—, así que lo que
 * se cuida acá es que la página traiga los que tiene que traer, en el orden de
 * la lista y sin repetir ni saltarse ninguno entre una página y la siguiente,
 * y que solo los vea quien puede ver la lista. Las reglas de la sorpresa sobre
 * estos mismos regalos están en ApiSurpriseTest.
 */
class ApiItemsPaginationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Wishlist}
     */
    private function listaConRegalos(int $cuantos): array
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);

        foreach (range(1, $cuantos) as $posicion) {
            WishlistItem::factory()->for($lista)->create(['position' => $posicion]);
        }

        return [$duenio, $lista];
    }

    public function test_show_says_how_many_gifts_there_are_without_bringing_them(): void
    {
        [$duenio, $lista] = $this->listaConRegalos(3);
        Sanctum::actingAs($duenio);

        $this->getJson(route('api.v1.wishlists.show', $lista))
            ->assertOk()
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonMissingPath('data.items');
    }

    public function test_pages_cover_every_gift_once_and_in_order(): void
    {
        [$duenio, $lista] = $this->listaConRegalos(26);
        Sanctum::actingAs($duenio);

        $vistos = collect();

        foreach ([1 => 10, 2 => 10, 3 => 6] as $pagina => $cuantos) {
            $respuesta = $this->getJson(route('api.v1.items.index', [$lista, 'page' => $pagina, 'per_page' => 10]))
                ->assertOk()
                ->assertJsonCount($cuantos, 'data')
                ->assertJsonPath('meta.current_page', $pagina)
                ->assertJsonPath('meta.last_page', 3)
                ->assertJsonPath('meta.per_page', 10)
                ->assertJsonPath('meta.total', 26);

            $vistos = $vistos->concat($respuesta->json('data.*.position'));
        }

        $this->assertSame(range(1, 26), $vistos->all());
    }

    public function test_without_per_page_it_brings_twenty(): void
    {
        [$duenio, $lista] = $this->listaConRegalos(26);
        Sanctum::actingAs($duenio);

        $this->getJson(route('api.v1.items.index', $lista))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_per_page_has_limits(): void
    {
        [$duenio, $lista] = $this->listaConRegalos(1);
        Sanctum::actingAs($duenio);

        foreach ([0, 51, 'diez'] as $invalido) {
            $this->getJson(route('api.v1.items.index', [$lista, 'per_page' => $invalido]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('per_page');
        }
    }

    public function test_the_page_links_keep_per_page(): void
    {
        [$duenio, $lista] = $this->listaConRegalos(26);
        Sanctum::actingAs($duenio);

        $siguiente = $this->getJson(route('api.v1.items.index', [$lista, 'per_page' => 10]))
            ->json('links.next');

        $this->assertStringContainsString('per_page=10', $siguiente);
        $this->assertStringContainsString('page=2', $siguiente);
    }

    public function test_someone_who_cannot_see_the_list_cannot_see_its_gifts(): void
    {
        $duenio = User::factory()->create();
        $privada = Wishlist::factory()->visibility(WishlistVisibility::PRIVATE)->create(['user_id' => $duenio->id]);
        WishlistItem::factory()->for($privada)->create(['position' => 1]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.items.index', $privada))->assertForbidden();
    }

    public function test_it_needs_a_token(): void
    {
        [, $lista] = $this->listaConRegalos(1);

        $this->getJson(route('api.v1.items.index', $lista))->assertUnauthorized();
    }
}
