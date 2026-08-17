<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * La búsqueda usa el índice FULLTEXT de products. InnoDB actualiza ese índice
 * recién al confirmar la transacción, así que este test no puede correr dentro
 * de una —por eso trunca en vez de usar RefreshDatabase—. Es también el motivo
 * de que la suite corra contra MariaDB y no contra SQLite.
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTruncation;

    public function test_the_search_finds_products_by_name(): void
    {
        Product::factory()->create([
            'name' => 'Peluche Pikachu 30 cm',
            'description' => 'El oficial de Pokemon Center.',
        ]);
        Product::factory()->create([
            'name' => 'Cafetera italiana Bialetti',
            'description' => 'Seis tazas, aluminio.',
        ]);

        $resultados = Product::search('Pikachu')->get();

        $this->assertCount(1, $resultados);
        $this->assertSame('Peluche Pikachu 30 cm', $resultados->first()->name);
    }

    public function test_the_search_also_looks_inside_the_description(): void
    {
        Product::factory()->create([
            'name' => 'Cafetera italiana Bialetti',
            'description' => 'Seis tazas, aluminio.',
        ]);

        $this->assertCount(1, Product::search('aluminio')->get());
    }

    public function test_the_search_can_be_narrowed_to_what_the_user_may_see(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        Product::factory()->privateFor($ana)->create([
            'name' => 'Taza de greca de Valparaiso',
            'description' => 'Esa azul con blanco de la feria.',
        ]);

        // Buscar y filtrar por visibilidad se combinan: el producto privado de
        // Ana no puede aparecerle a Bruno aunque calce con el término.
        $this->assertCount(1, Product::search('greca')->visibleTo($ana)->get());
        $this->assertCount(0, Product::search('greca')->visibleTo($bruno)->get());
    }
}
