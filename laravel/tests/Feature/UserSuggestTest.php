<?php

namespace Tests\Feature;

use App\Enums\WishlistVisibility;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las sugerencias mientras se escribe, que devuelven JSON.
 *
 * `FollowAccessTest` ya cubre que el scope no encuentre a nadie por su nombre
 * real; esto cubre el endpoint, que es lo que de verdad está expuesto. Un
 * `orWhere('name', ...)` agregado acá «para que sea más fácil encontrar gente»
 * volvería decorativo el interruptor de ocultar el nombre, y ninguna pantalla
 * se vería rota.
 */
class UserSuggestTest extends TestCase
{
    use RefreshDatabase;

    private function sugerencias(User $quienBusca, string $termino): array
    {
        return $this->actingAs($quienBusca)
            ->getJson(route('users.suggest', ['q' => $termino]))
            ->assertOk()
            ->json('usuarios');
    }

    public function test_the_suggestions_find_a_person_by_the_start_of_their_username(): void
    {
        $yo = User::factory()->create();
        User::factory()->create(['username' => 'pelusa88', 'name' => 'Ana Rojas']);

        $encontrados = $this->sugerencias($yo, 'pelu');

        $this->assertCount(1, $encontrados);
        $this->assertSame('@pelusa88', $encontrados[0]['handle']);
    }

    public function test_the_arroba_is_ignored_when_typed(): void
    {
        $yo = User::factory()->create();
        User::factory()->create(['username' => 'pelusa88']);

        $this->assertCount(1, $this->sugerencias($yo, '@pelusa'));
    }

    public function test_the_suggestions_never_return_someone_by_their_real_name(): void
    {
        $yo = User::factory()->create();
        User::factory()->create([
            'username' => 'pelusa88',
            'name' => 'Ana Rojas',
            // Incluso quien muestra su nombre se busca solo por el arroba: lo
            // que la persona eligió es que se lea, no que sea la puerta.
            'show_name' => true,
        ]);

        $this->assertSame([], $this->sugerencias($yo, 'Ana Rojas'));
        $this->assertSame([], $this->sugerencias($yo, 'Rojas'));
        $this->assertSame([], $this->sugerencias($yo, 'ana'));
    }

    /**
     * Si buscara por correo, bastaría con probar direcciones para saber quién
     * está registrado en la plataforma.
     */
    public function test_the_suggestions_never_return_someone_by_their_email(): void
    {
        $yo = User::factory()->create();
        User::factory()->create(['username' => 'pelusa88', 'email' => 'contacto@ejemplo.cl']);

        $this->assertSame([], $this->sugerencias($yo, 'contacto'));
        $this->assertSame([], $this->sugerencias($yo, 'ejemplo.cl'));
    }

    /**
     * Con una o dos letras se devolvería media plataforma, que es justo lo que
     * la búsqueda por arroba evita.
     */
    public function test_fewer_than_three_letters_suggests_nobody(): void
    {
        $yo = User::factory()->create();
        User::factory()->create(['username' => 'pelusa88']);

        $this->assertSame([], $this->sugerencias($yo, 'pe'));
        $this->assertSame([], $this->sugerencias($yo, '@pe'));
        $this->assertSame([], $this->sugerencias($yo, ''));
        // Tres letras sí, para que el límite quede fijado por los dos lados.
        $this->assertCount(1, $this->sugerencias($yo, 'pel'));
    }

    public function test_the_suggestions_never_include_yourself(): void
    {
        $yo = User::factory()->create(['username' => 'pelusa88']);
        User::factory()->create(['username' => 'pelusa99']);

        $encontrados = $this->sugerencias($yo, 'pelusa');

        $this->assertCount(1, $encontrados);
        $this->assertSame('@pelusa99', $encontrados[0]['handle']);
    }

    /**
     * El nombre viaja solo si su dueño lo dejó a la vista. El JSON es más fácil
     * de mirar que una vista: acá un `name` de más es un nombre filtrado.
     */
    public function test_a_hidden_name_never_travels_in_the_payload(): void
    {
        $yo = User::factory()->create();
        User::factory()->create(['username' => 'pelusa88', 'name' => 'Ana Rojas', 'show_name' => false]);
        User::factory()->create(['username' => 'pelusa99', 'name' => 'Bruno Díaz', 'show_name' => true]);

        $encontrados = collect($this->sugerencias($yo, 'pelusa'))->keyBy('handle');

        $this->assertNull($encontrados['@pelusa88']['nombre']);
        $this->assertSame('Bruno Díaz', $encontrados['@pelusa99']['nombre']);
        $this->assertStringNotContainsString('Ana Rojas', json_encode($encontrados));
    }

    /**
     * El contador que se muestra al lado de cada persona cuenta solo listas
     * públicas: decir «tiene 3 listas» cuando dos son privadas ya es contar
     * algo que su dueño no contó.
     */
    public function test_the_shown_count_only_includes_public_wishlists(): void
    {
        $yo = User::factory()->create();
        $otra = User::factory()->create(['username' => 'pelusa88']);

        Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $otra->id]);
        Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->count(2)
            ->create(['user_id' => $otra->id]);

        $encontrados = $this->sugerencias($yo, 'pelusa');

        $this->assertSame(1, $encontrados[0]['listas']);
    }
}
