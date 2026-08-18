<?php

namespace Tests\Feature;

use App\Enums\ItemPriority;
use App\Enums\WishlistVisibility;
use App\Jobs\ShrinkStoredImage;
use App\Models\Category;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/**
 * Achicar las fotos subidas.
 *
 * Lo que se prueba acá es lo que nadie ve: la foto se muestra igual de bien
 * achicada que sin achicar, así que si el job dejara de correr —el worker
 * caído, el job encolado y nunca despachado— la aplicación seguiría pareciendo
 * correcta mientras cada visita se baja megabytes de más.
 */
class ShrinkStoredImageTest extends TestCase
{
    use RefreshDatabase;

    private function medir(string $ruta): array
    {
        $imagen = ImageManager::usingDriver(Driver::class)
            ->decodeBinary(Storage::disk('public')->get($ruta));

        return [$imagen->width(), $imagen->height()];
    }

    public function test_a_photo_bigger_than_the_limit_is_scaled_down(): void
    {
        Storage::fake('public');

        // Las proporciones de una foto de celular, en chico para que el test
        // no tarde: lo que importa es que el lado largo baje al tope.
        Storage::disk('public')->put(
            'productos/foto.jpg',
            (string) UploadedFile::fake()->image('foto.jpg', 2000, 1500)->get()
        );

        ShrinkStoredImage::forProductPhoto('productos/foto.jpg')->handle();

        $this->assertSame(
            [ShrinkStoredImage::LADO_PRODUCTO, 600],
            $this->medir('productos/foto.jpg'),
            'El lado largo debería quedar en el tope y la proporción intacta.'
        );
    }

    public function test_scaling_down_makes_the_file_much_lighter(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'perfiles/foto.jpg',
            (string) UploadedFile::fake()->image('foto.jpg', 3000, 3000)->get()
        );

        $antes = Storage::disk('public')->size('perfiles/foto.jpg');

        ShrinkStoredImage::forAvatar('perfiles/foto.jpg')->handle();

        $despues = Storage::disk('public')->size('perfiles/foto.jpg');

        // El número exacto depende de la imagen; lo que no depende de nada es
        // que 3000 px pesen bastante más que 320.
        $this->assertLessThan($antes, $despues);
    }

    /**
     * Una imagen que ya cabe se deja en paz. Si se agrandara, una foto chica
     * saldría borrosa después de subirla, que es peor que dejarla como estaba.
     */
    public function test_a_photo_that_already_fits_is_not_enlarged(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'perfiles/chica.jpg',
            (string) UploadedFile::fake()->image('chica.jpg', 100, 80)->get()
        );

        ShrinkStoredImage::forAvatar('perfiles/chica.jpg')->handle();

        $this->assertSame([100, 80], $this->medir('perfiles/chica.jpg'));
    }

    /**
     * La cola reintenta un job que se cayó después de haber hecho su trabajo.
     * Si cada pasada volviera a codificar la imagen más chica, o peor, la
     * degradara, un reintento saldría caro.
     */
    public function test_running_it_twice_leaves_the_same_size(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'productos/foto.jpg',
            (string) UploadedFile::fake()->image('foto.jpg', 2000, 1500)->get()
        );

        ShrinkStoredImage::forProductPhoto('productos/foto.jpg')->handle();
        $primera = $this->medir('productos/foto.jpg');

        ShrinkStoredImage::forProductPhoto('productos/foto.jpg')->handle();

        $this->assertSame($primera, $this->medir('productos/foto.jpg'));
    }

    /**
     * Entre que se encola y llega su turno, la foto pudo desaparecer: alguien
     * cambió su avatar dos veces seguidas y el borrado de la anterior ya pasó.
     * No es un error, y no debe terminar en `failed_jobs`.
     */
    public function test_a_photo_that_vanished_before_its_turn_is_not_an_error(): void
    {
        Storage::fake('public');

        ShrinkStoredImage::forAvatar('perfiles/ya-no-esta.jpg')->handle();

        Storage::disk('public')->assertMissing('perfiles/ya-no-esta.jpg');
    }

    /**
     * El formato se conserva porque la ruta ya está publicada en el HTML: un
     * png que volviera como jpg dejaría la fila apuntando a un archivo que no
     * existe.
     */
    public function test_the_file_keeps_its_format_and_its_path(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'productos/foto.png',
            (string) UploadedFile::fake()->image('foto.png', 1200, 1200)->get()
        );

        ShrinkStoredImage::forProductPhoto('productos/foto.png')->handle();

        Storage::disk('public')->assertExists('productos/foto.png');

        $imagen = ImageManager::usingDriver(Driver::class)
            ->decodeBinary(Storage::disk('public')->get('productos/foto.png'));

        $this->assertSame('image/png', $imagen->origin()->mediaType());
    }

    // --- Que además se encole ------------------------------------------------

    public function test_uploading_a_profile_photo_queues_the_shrink(): void
    {
        Storage::fake('public');
        Queue::fake();

        $usuario = User::factory()->create();

        $this->actingAs($usuario)->patch(route('profile.update'), [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'perfil_publico' => '1',
            'avatar' => UploadedFile::fake()->image('mia.jpg'),
        ])->assertSessionHasNoErrors();

        Queue::assertPushed(ShrinkStoredImage::class, 1);
    }

    public function test_adding_a_gift_with_a_photo_queues_the_shrink(): void
    {
        Storage::fake('public');
        Queue::fake();

        $duenio = User::factory()->create();
        $lista = Wishlist::factory()
            ->visibility(WishlistVisibility::PUBLIC)
            ->create(['user_id' => $duenio->id]);

        $this->actingAs($duenio)->post(route('items.store', $lista), [
            'name' => 'Una tetera',
            'category_id' => Category::factory()->create()->id,
            'priority' => ItemPriority::MEDIUM->label(),
            'image' => UploadedFile::fake()->image('tetera.jpg'),
        ])->assertSessionHasNoErrors();

        Queue::assertPushed(ShrinkStoredImage::class, 1);
    }

    /**
     * Sin foto no hay nada que achicar, y encolar un job que no tiene trabajo
     * solo ensucia la cola.
     */
    public function test_saving_the_profile_without_a_photo_queues_nothing(): void
    {
        Queue::fake();

        $usuario = User::factory()->create();

        $this->actingAs($usuario)->patch(route('profile.update'), [
            'name' => 'Otro Nombre',
            'username' => $usuario->username,
            'perfil_publico' => '1',
        ])->assertSessionHasNoErrors();

        Queue::assertNothingPushed();
    }
}
