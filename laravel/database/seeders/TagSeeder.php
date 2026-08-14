<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Tags base. A diferencia de las categorías, que son pocas y cerradas, los
     * tags crecen con el uso: acá solo va un punto de partida para que la
     * búsqueda tenga con qué trabajar desde el primer día.
     */
    public function run(): void
    {
        $tags = [
            // Franquicias y marcas
            'Pokémon', 'Pikachu', 'Nintendo', 'LEGO', 'Star Wars', 'Marvel',
            'Sony', 'Apple', 'Nike',
            // Aficiones
            'Café', 'Cocina', 'Running', 'Fitness', 'Lectura', 'Jardinería',
            'Fotografía', 'Camping', 'Outdoor',
            // Géneros y formatos
            'Ciencia ficción', 'Literatura', 'Clásicos', 'Rock', 'Vinilos',
            'Videojuegos', 'Consolas', 'Audio', 'Instrumentos',
            'Estrategia', 'Familiar', 'Coleccionable', 'Peluches',
            // Otros
            'Gatos', 'Perros', 'Pintura', 'Handmade', 'Sustentable',
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($tag)],
                ['name' => $tag]
            );
        }

        $this->command->info('Tags: '.Tag::count());
    }
}
