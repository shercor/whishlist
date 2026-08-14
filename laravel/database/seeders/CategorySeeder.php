<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Categorías base del catálogo. Son datos de referencia, no de demo:
     * se siembran en cualquier entorno.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Juguetes', 'icon' => 'teddy-bear', 'description' => 'Peluches, figuras, sets de construcción.'],
            ['name' => 'Tecnología', 'icon' => 'cpu', 'description' => 'Electrónica, consolas, accesorios.'],
            ['name' => 'Libros', 'icon' => 'book', 'description' => 'Narrativa, ensayo, cómics.'],
            ['name' => 'Ropa', 'icon' => 'shirt', 'description' => 'Vestuario y calzado.'],
            ['name' => 'Hogar', 'icon' => 'home', 'description' => 'Cocina, decoración, orden.'],
            ['name' => 'Comida', 'icon' => 'cake', 'description' => 'Dulces, despensa, delicatessen.'],
            ['name' => 'Deportes', 'icon' => 'dumbbell', 'description' => 'Equipamiento y ropa deportiva.'],
            ['name' => 'Música', 'icon' => 'music', 'description' => 'Instrumentos, vinilos, audio.'],
            ['name' => 'Belleza', 'icon' => 'sparkles', 'description' => 'Cuidado personal y cosmética.'],
            ['name' => 'Mascotas', 'icon' => 'paw', 'description' => 'Todo para el regalón de la casa.'],
            ['name' => 'Juegos de mesa', 'icon' => 'dice', 'description' => 'Estrategia, party games, cartas.'],
            ['name' => 'Arte', 'icon' => 'palette', 'description' => 'Materiales y láminas.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'active' => true,
                ]
            );
        }

        $this->command->info('Categorías: '.Category::count());
    }
}
