<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Catálogo público inicial. Todos nacen con is_public = true: son los
     * productos curados que cualquiera puede buscar y agregar a su lista.
     * Los productos que los usuarios creen a mano nacerán privados.
     */
    public function run(): void
    {
        foreach ($this->catalog() as $row) {
            $category = Category::where('slug', $row['category'])->first();

            if (! $category) {
                $this->command->warn("Categoría no encontrada: {$row['category']}, se omite {$row['name']}");

                continue;
            }

            $product = Product::updateOrCreate(
                ['name' => $row['name'], 'category_id' => $category->id],
                [
                    'description' => $row['description'],
                    'reference_price' => $row['price'],
                    'currency' => 'CLP',
                    'is_public' => true,
                    'created_by_user_id' => null,
                ]
            );

            $tagIds = Tag::whereIn('slug', array_map(
                fn (string $tag) => Str::slug($tag),
                $row['tags']
            ))->pluck('id');

            $product->tags()->sync($tagIds);
        }

        $this->syncTagCounters();

        $this->command->info('Productos de catálogo: '.Product::where('is_public', true)->count());
    }

    /**
     * Recalcula el contador desnormalizado de tags de una sola pasada, en vez
     * de ir sumando uno a uno mientras se siembra.
     */
    private function syncTagCounters(): void
    {
        // El alias va renombrado a propósito: withCount('products') generaría
        // uno llamado products_count, idéntico a la columna que queremos
        // escribir, y uno pisaría al otro en el resultado.
        Tag::withCount('products as computed_products_count')
            ->get()
            ->each(function (Tag $tag) {
                $tag->update(['products_count' => $tag->computed_products_count]);
            });
    }

    /**
     * @return array<int, array{name: string, category: string, description: string, price: int, tags: array<int, string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Peluche Pikachu 30 cm',
                'category' => 'juguetes',
                'description' => 'Peluche oficial de Pikachu, felpa suave, 30 centímetros.',
                'price' => 24990,
                'tags' => ['Pokémon', 'Pikachu', 'Nintendo', 'Peluches'],
            ],
            [
                'name' => 'LEGO Millennium Falcon',
                'category' => 'juguetes',
                'description' => 'Set de construcción de 1351 piezas de la nave de Han Solo.',
                'price' => 179990,
                'tags' => ['LEGO', 'Star Wars', 'Coleccionable'],
            ],
            [
                'name' => 'Figura Iron Man escala 1:6',
                'category' => 'juguetes',
                'description' => 'Figura articulada de colección con base y accesorios.',
                'price' => 89990,
                'tags' => ['Marvel', 'Coleccionable'],
            ],
            [
                'name' => 'Nintendo Switch OLED',
                'category' => 'tecnologia',
                'description' => 'Consola híbrida con pantalla OLED de 7 pulgadas.',
                'price' => 389990,
                'tags' => ['Nintendo', 'Consolas', 'Videojuegos'],
            ],
            [
                'name' => 'Audífonos Sony WH-1000XM5',
                'category' => 'tecnologia',
                'description' => 'Over-ear con cancelación de ruido y 30 horas de batería.',
                'price' => 329990,
                'tags' => ['Sony', 'Audio'],
            ],
            [
                'name' => 'Kindle Paperwhite',
                'category' => 'tecnologia',
                'description' => 'Lector de tinta electrónica de 6,8 pulgadas, resistente al agua.',
                'price' => 149990,
                'tags' => ['Lectura', 'Apple'],
            ],
            [
                'name' => 'Cien años de soledad',
                'category' => 'libros',
                'description' => 'Edición conmemorativa de la novela de Gabriel García Márquez.',
                'price' => 19990,
                'tags' => ['Literatura', 'Clásicos'],
            ],
            [
                'name' => 'Dune',
                'category' => 'libros',
                'description' => 'La novela de Frank Herbert, edición de bolsillo.',
                'price' => 15990,
                'tags' => ['Ciencia ficción', 'Literatura'],
            ],
            [
                'name' => 'Cafetera italiana Bialetti 6 tazas',
                'category' => 'hogar',
                'description' => 'Moka clásica de aluminio para 6 tazas.',
                'price' => 34990,
                'tags' => ['Café', 'Cocina'],
            ],
            [
                'name' => 'Set de cuchillos japoneses',
                'category' => 'hogar',
                'description' => 'Tres piezas de acero damasco con mango de pakkawood.',
                'price' => 119990,
                'tags' => ['Cocina', 'Handmade'],
            ],
            [
                'name' => 'Maceta autorregante de cerámica',
                'category' => 'hogar',
                'description' => 'Con depósito de agua para dos semanas de autonomía.',
                'price' => 18990,
                'tags' => ['Jardinería', 'Sustentable'],
            ],
            [
                'name' => 'Zapatillas Nike Pegasus 41',
                'category' => 'deportes',
                'description' => 'Zapatilla de running para entrenamiento diario.',
                'price' => 109990,
                'tags' => ['Running', 'Nike', 'Fitness'],
            ],
            [
                'name' => 'Mancuernas ajustables 24 kg',
                'category' => 'deportes',
                'description' => 'Par de mancuernas con discos intercambiables.',
                'price' => 159990,
                'tags' => ['Fitness'],
            ],
            [
                'name' => 'Carpa 2 personas ultraliviana',
                'category' => 'deportes',
                'description' => 'Doble pared, 1,9 kg, para trekking de varios días.',
                'price' => 139990,
                'tags' => ['Camping', 'Outdoor'],
            ],
            [
                'name' => 'Vinilo OK Computer - Radiohead',
                'category' => 'musica',
                'description' => 'Reedición en vinilo de 180 gramos.',
                'price' => 44990,
                'tags' => ['Vinilos', 'Rock'],
            ],
            [
                'name' => 'Ukelele concierto de caoba',
                'category' => 'musica',
                'description' => 'Ukelele tamaño concierto con funda y afinador.',
                'price' => 69990,
                'tags' => ['Instrumentos', 'Handmade'],
            ],
            [
                'name' => 'Catan',
                'category' => 'juegos-de-mesa',
                'description' => 'El clásico de comercio y colonización, de 3 a 4 jugadores.',
                'price' => 39990,
                'tags' => ['Estrategia', 'Familiar'],
            ],
            [
                'name' => 'Dixit',
                'category' => 'juegos-de-mesa',
                'description' => 'Juego de imaginación y asociación con cartas ilustradas.',
                'price' => 32990,
                'tags' => ['Familiar'],
            ],
            [
                'name' => 'Rascador para gatos de sisal',
                'category' => 'mascotas',
                'description' => 'Torre de 1,2 metros con dos plataformas y cueva.',
                'price' => 54990,
                'tags' => ['Gatos'],
            ],
            [
                'name' => 'Cama ortopédica para perro talla L',
                'category' => 'mascotas',
                'description' => 'Espuma viscoelástica con funda lavable.',
                'price' => 64990,
                'tags' => ['Perros'],
            ],
            [
                'name' => 'Set de acuarelas 24 colores',
                'category' => 'arte',
                'description' => 'Pastillas de acuarela profesional con pincel recargable.',
                'price' => 42990,
                'tags' => ['Pintura', 'Handmade'],
            ],
            [
                'name' => 'Cámara instantánea Instax Mini 12',
                'category' => 'arte',
                'description' => 'Cámara de fotos instantáneas con flash automático.',
                'price' => 89990,
                'tags' => ['Fotografía'],
            ],
            [
                'name' => 'Chaqueta cortaviento impermeable',
                'category' => 'ropa',
                'description' => 'Membrana de 10.000 mm de columna de agua, plegable.',
                'price' => 79990,
                'tags' => ['Outdoor', 'Camping'],
            ],
            [
                'name' => 'Caja de trufas artesanales',
                'category' => 'comida',
                'description' => 'Doce trufas de chocolate belga hechas a mano.',
                'price' => 22990,
                'tags' => ['Handmade'],
            ],
            [
                'name' => 'Café de especialidad 1 kg',
                'category' => 'comida',
                'description' => 'Grano de origen, tueste medio, molienda a pedido.',
                'price' => 28990,
                'tags' => ['Café', 'Sustentable'],
            ],
        ];
    }
}
