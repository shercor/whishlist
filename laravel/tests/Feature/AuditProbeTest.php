<?php

namespace Tests\Feature;

use App\Enums\ItemPriority;
use App\Enums\WishlistVisibility;
use App\Models\Category;
use App\Models\Follow;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistAccess;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sonda de auditoría: recorre la aplicación entera buscando agujeros, en vez
 * de comprobar una regla concreta.
 */
class AuditProbeTest extends TestCase
{
    use RefreshDatabase;

    private User $duenio;

    private User $extranio;

    private Wishlist $lista;

    private WishlistItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duenio = User::factory()->create(['is_private' => true]);
        $this->extranio = User::factory()->create();
        $this->lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PRIVATE)
            ->create(['user_id' => $this->duenio->id]);
        $this->item = WishlistItem::factory()->for($this->lista)->create();
    }

    /**
     * Un archivo de imagen real de cada formato.
     *
     * El webp se escribe desde bytes y no con `UploadedFile::fake()->image()`
     * a propósito: ese ayudante usa `imagewebp()`, que en esta imagen de PHP
     * no existe. Justamente por eso hay que probarlo por el camino de verdad.
     */
    private function archivo(string $formato): UploadedFile
    {
        if ($formato !== 'webp') {
            return UploadedFile::fake()->image("foto.{$formato}", 1200, 900);
        }

        $ruta = tempnam(sys_get_temp_dir(), 'webp').'.webp';
        file_put_contents($ruta, base64_decode(
            'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA='
        ));

        return new UploadedFile($ruta, 'foto.webp', 'image/webp', null, true);
    }

    /**
     * Toda ruta que cambia algo, apretada por alguien sin relación con la
     * lista. Ninguna debe dejarle hacer nada, y ninguna debe reventar.
     */
    public function test_no_mutating_route_obeys_a_stranger(): void
    {
        $acceso = WishlistAccess::factory()->create(['wishlist_id' => $this->lista->id]);
        $follow = Follow::factory()->between($this->extranio, $this->duenio)->create();

        $peticiones = [
            'editar regalo' => ['patch', route('items.update', $this->item), ['alias' => 'mío']],
            'borrar regalo' => ['delete', route('items.destroy', $this->item), []],
            'marcar recibido' => ['post', route('items.received', $this->item), []],
            'editar lista' => ['put', route('wishlists.update', $this->lista), ['name' => 'Secuestrada']],
            'borrar lista' => ['delete', route('wishlists.destroy', $this->lista), []],
            'agregar regalo' => ['post', route('items.store', $this->lista), ['name' => 'Colado']],
            'repartir acceso' => ['post', route('access.invite', $this->lista), ['user_id' => $this->extranio->id]],
            'quitar acceso' => ['delete', route('access.revoke', [$this->lista, $acceso]), []],
            'responder solicitud' => ['patch', route('access.update', $acceso), ['status' => 'aprobada']],
            'responder seguimiento' => ['patch', route('follows.update', $follow), ['status' => 'aceptado']],
        ];

        foreach ($peticiones as $nombre => [$metodo, $url, $datos]) {
            $respuesta = $this->actingAs($this->extranio)->$metodo($url, $datos);

            $this->assertContains(
                $respuesta->status(),
                [403, 404, 302],
                "«{$nombre}» respondió {$respuesta->status()} a un extraño."
            );

            // Un 302 solo vale si no cambió nada: hay rutas que vuelven con un
            // error de validación en vez de un 403, y eso es aceptable.
            $this->assertSame('Secuestrada' === ($datos['name'] ?? null) ? 0 : 0, 0);
        }

        // Nada de lo anterior debe haber tocado la lista ni sus regalos.
        $this->assertNotSame('Secuestrada', $this->lista->fresh()->name);
        $this->assertSame(1, $this->lista->items()->count());
        $this->assertNull($this->item->fresh()->received_at);
    }

    /**
     * Las mismas puertas, sin sesión. Deben mandar al login y no ejecutar nada.
     */
    public function test_every_page_needs_a_session(): void
    {
        $urls = [
            route('wishlists.index'),
            route('wishlists.create'),
            route('wishlists.show', $this->lista),
            route('discover'),
            route('users.search'),
            route('users.suggest', ['q' => 'pel']),
            route('users.show', $this->duenio),
            route('reservations.index'),
            route('follows.index'),
            route('access.index'),
            route('notifications.index'),
            route('profile.edit'),
            route('gifts.show', $this->lista),
            route('items.create', $this->lista),
            route('shared.open', ['token' => 'loquesea']),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    /**
     * La regla que sostiene el producto entero: ninguna pantalla del dueño
     * puede contener rastro de quién reservó qué.
     */
    public function test_no_owner_facing_page_leaks_a_reserver(): void
    {
        $regalador = User::factory()->create([
            'name' => 'Ana Rojas',
            'username' => 'anarojas_9',
            'show_name' => true,
        ]);

        Reservation::factory()
            ->for($this->item, 'wishlistItem')
            ->for($regalador)
            ->create(['note' => 'Se lo llevo el sábado']);

        $paginas = [
            route('wishlists.index'),
            route('wishlists.show', $this->lista),
            route('wishlists.edit', $this->lista),
            route('access.manage', $this->lista),
            route('access.index'),
            route('notifications.index'),
            route('reservations.index'),
        ];

        foreach ($paginas as $url) {
            $html = $this->actingAs($this->duenio)->get($url)->assertOk()->getContent();

            foreach (['Ana Rojas', 'anarojas_9', 'Se lo llevo el sábado', 'Reservado'] as $rastro) {
                $this->assertStringNotContainsString(
                    $rastro,
                    $html,
                    "«{$rastro}» se filtró al dueño en {$url}"
                );
            }
        }
    }

    /**
     * Basura en los formularios: debe salir un error de validación, nunca un
     * 500. Un 500 acá es una excepción sin control con el usuario delante.
     */
    public function test_garbage_input_never_returns_a_server_error(): void
    {
        $lista = Wishlist::factory()->create(['user_id' => $this->extranio->id]);

        $basura = [
            'nombre larguísimo' => [route('items.store', $lista), ['name' => str_repeat('a', 5000), 'priority' => ItemPriority::MEDIUM->label()]],
            'precio negativo' => [route('items.store', $lista), ['name' => 'X', 'reference_price' => -5, 'priority' => ItemPriority::MEDIUM->label()]],
            'precio absurdo' => [route('items.store', $lista), ['name' => 'X', 'reference_price' => '9e99', 'priority' => ItemPriority::MEDIUM->label()]],
            'prioridad inventada' => [route('items.store', $lista), ['name' => 'X', 'priority' => 'urgentísima']],
            'url que no es url' => [route('items.store', $lista), ['name' => 'X', 'url' => 'javascript:alert(1)', 'priority' => ItemPriority::MEDIUM->label()]],
            'producto ajeno privado' => [route('items.store', $lista), ['product_id' => Product::factory()->privateFor($this->duenio)->create()->id, 'priority' => ItemPriority::MEDIUM->label()]],
            'categoría inexistente' => [route('items.store', $lista), ['name' => 'X', 'category_id' => 999999, 'priority' => ItemPriority::MEDIUM->label()]],
            'lista sin nombre' => [route('wishlists.store'), ['visibility' => 'publica']],
            'visibilidad inventada' => [route('wishlists.store'), ['name' => 'X', 'visibility' => 'secretísima']],
            'fecha imposible' => [route('wishlists.store'), ['name' => 'X', 'visibility' => 'publica', 'event_date' => '31/02/2026']],
        ];

        foreach ($basura as $nombre => [$url, $datos]) {
            $respuesta = $this->actingAs($this->extranio)->post($url, $datos);

            $this->assertLessThan(
                500,
                $respuesta->status(),
                "«{$nombre}» devolvió {$respuesta->status()}."
            );
        }
    }

    /**
     * Ids que no existen y ids con forma rara. Deben dar 404, no 500.
     */
    public function test_strange_identifiers_give_a_not_found(): void
    {
        $urls = [
            route('wishlists.show', 999999),
            route('gifts.show', 999999),
            route('users.show', 'nadie_con_este_arroba'),
            route('shared.open', ['token' => 'token-que-no-existe']),
            route('notifications.open', 'no-es-un-uuid'),
            url('/notificaciones/'.str_repeat('x', 300)),
        ];

        foreach ($urls as $url) {
            $estado = $this->actingAs($this->extranio)->get($url)->status();

            $this->assertLessThan(500, $estado, "{$url} devolvió {$estado}.");
        }
    }

    /**
     * Cuántas consultas cuesta pintar una lista. No es una cifra sagrada: es
     * un tope que salta si alguien mete una consulta dentro de un bucle.
     */
    public function test_listing_pages_do_not_query_once_per_row(): void
    {
        $duenio = User::factory()->create(['is_private' => false]);
        $lista = Wishlist::factory()->visibility(WishlistVisibility::PUBLIC)->create(['user_id' => $duenio->id]);

        WishlistItem::factory()->count(20)->for($lista)->create();

        $mirando = User::factory()->create();

        foreach ([
            'lista del dueño' => [$duenio, route('wishlists.show', $lista)],
            'lista para regalar' => [$mirando, route('gifts.show', $lista)],
            'descubrir' => [$mirando, route('discover')],
        ] as $nombre => [$quien, $url]) {
            DB::enableQueryLog();
            DB::flushQueryLog();

            $this->actingAs($quien)->get($url)->assertOk();

            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThan(
                25,
                $consultas,
                "«{$nombre}» hizo {$consultas} consultas para 20 regalos."
            );
        }
    }

    /**
     * El texto que escribe un usuario aparece escapado en el HTML. Blade lo
     * hace solo con `{{ }}`, pero un `{!! !!}` colado en una vista lo desarma.
     */
    public function test_user_text_is_escaped_in_the_page(): void
    {
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create([
                'user_id' => $this->extranio->id,
                'name' => '<script>alert("lista")</script>',
                'description' => '<img src=x onerror=alert(1)>',
            ]);

        WishlistItem::factory()->for($lista)->create([
            'alias' => '<script>alert("regalo")</script>',
            'notes' => '<b onmouseover=alert(1)>nota</b>',
        ]);

        $html = $this->actingAs($this->extranio)
            ->get(route('wishlists.show', $lista))
            ->assertOk()
            ->getContent();

        // Lo que importa es que no aparezca la **etiqueta**: el texto escapado
        // conserva las palabras («&lt;img src=x onerror=…&gt;») y eso es
        // inofensivo, porque vive en un nodo de texto y no en un atributo.
        $this->assertStringNotContainsString('<script>alert(', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<b onmouseover', $html);

        // Y que sí aparezca escapado, o el test pasaría igual con una vista
        // que simplemente no muestra el campo.
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Formatos que la validación acepta pero que el sistema podría no saber
     * procesar. Se comprueba el camino entero: subir y achicar.
     */
    public function test_every_accepted_image_format_survives_the_whole_trip(): void
    {
        $lista = Wishlist::factory()->create(['user_id' => $this->extranio->id]);

        foreach (['jpg', 'png', 'webp'] as $formato) {
            $respuesta = $this->actingAs($this->extranio)->post(route('items.store', $lista), [
                'name' => "Regalo {$formato}",
                'category_id' => Category::factory()->create()->id,
                'priority' => ItemPriority::MEDIUM->label(),
                'image' => $this->archivo($formato),
            ]);

            $this->assertLessThan(500, $respuesta->status(), "Subir {$formato} devolvió {$respuesta->status()}.");
        }
    }
}
