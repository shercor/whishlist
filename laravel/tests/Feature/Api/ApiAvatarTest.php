<?php

namespace Tests\Feature\Api;

use App\Jobs\ShrinkStoredImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La foto de perfil por la API.
 *
 * El guardado en sí ya lo cuida `AvatarStorageTest` por el camino web, y los
 * dos usan el mismo `AvatarService`. Lo que se prueba acá es lo que la API
 * agrega y podría romperse sola: que el endpoint exista con su verbo, que
 * devuelva la URL nueva —que el cliente no puede adivinar—, que valide lo que
 * llega y que pida token.
 */
class ApiAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_photo_stores_it_and_answers_with_its_url(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $respuesta = $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('mia.jpg'),
        ])->assertOk();

        $ruta = $usuario->fresh()->avatar_path;

        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);

        // Lo que justifica devolver el perfil en vez de un 204: el nombre del
        // archivo lo inventa el servidor, así que sin esto el cliente no sabe
        // qué pintar.
        $this->assertNotNull($respuesta->json('data.avatar'));
        $this->assertStringContainsString($ruta, $respuesta->json('data.avatar'));
    }

    public function test_uploading_a_second_photo_deletes_the_first(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('primera.jpg'),
        ])->assertOk();
        $primera = $usuario->fresh()->avatar_path;

        $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('segunda.jpg'),
        ])->assertOk();
        $segunda = $usuario->fresh()->avatar_path;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertExists($segunda);
        Storage::disk('public')->assertMissing($primera);
    }

    public function test_deleting_the_photo_clears_the_field_and_the_file(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('unica.jpg'),
        ])->assertOk();
        $ruta = $usuario->fresh()->avatar_path;

        $this->deleteJson(route('api.v1.me.avatar.destroy'))
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        $this->assertNull($usuario->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($ruta);
    }

    /**
     * Quitar una foto que no está es el estado que el cliente pedía, no un
     * error: reintentar tras una red caída no debe devolver 500.
     */
    public function test_deleting_a_photo_that_was_never_there_is_not_an_error(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create(['avatar_path' => null]);
        Sanctum::actingAs($usuario);

        $this->deleteJson(route('api.v1.me.avatar.destroy'))
            ->assertOk()
            ->assertJsonPath('data.avatar', null);
    }

    /**
     * El achicado vive en la cola. Si el endpoint guardara la foto sin
     * encolarlo, no se rompería nada visible: las fotos subidas por la API
     * quedarían pesadas para siempre, y solo se notaría en la factura del
     * disco.
     */
    public function test_uploading_queues_the_shrinking_job(): void
    {
        Storage::fake('public');
        Queue::fake();
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('pesada.jpg'),
        ])->assertOk();

        Queue::assertPushed(ShrinkStoredImage::class, 1);
    }

    public function test_something_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        // La extensión miente a propósito: la regla 'image' mira el contenido.
        $this->putJson(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->create('script.png', 12, 'text/plain'),
        ])->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->assertNull($usuario->fresh()->avatar_path);
    }

    public function test_the_field_is_required(): void
    {
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->putJson(route('api.v1.me.avatar.store'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_both_endpoints_need_a_token(): void
    {
        $this->putJson(route('api.v1.me.avatar.store'))->assertUnauthorized();
        $this->deleteJson(route('api.v1.me.avatar.destroy'))->assertUnauthorized();
    }

    /**
     * Editar el perfil no toca la foto. Es la mitad de la razón de que este
     * endpoint exista: `PATCH me` deja en paz lo que no le mandan, y una foto
     * no puede depender de que el cliente se acuerde de reenviarla en cada
     * cambio de nombre.
     *
     * Técnicamente la foto **sí** cabría en el PATCH —PHP 8.4 desarma
     * multipart en cualquier verbo—, así que esto no fija una limitación sino
     * una decisión: el perfil se edita en json y el binario tiene su sitio.
     */
    public function test_editing_the_profile_does_not_touch_the_photo(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        $this->put(route('api.v1.me.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('la_mia.jpg'),
        ])->assertOk();
        $ruta = $usuario->fresh()->avatar_path;

        $this->patchJson(route('api.v1.me.update'), ['name' => 'Otro Nombre'])
            ->assertOk();

        $this->assertSame($ruta, $usuario->fresh()->avatar_path);
        Storage::disk('public')->assertExists($ruta);
    }

    /**
     * PUT promete que repetirlo no acumula. Importa porque un cliente que
     * pierde la red reintenta, y si cada intento dejara un archivo más, el
     * disco se llenaría de fotos que nadie mira.
     */
    public function test_uploading_twice_leaves_one_photo_on_disk(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);

        foreach (['una.jpg', 'otra.jpg', 'otra_mas.jpg'] as $nombre) {
            $this->put(route('api.v1.me.avatar.store'), [
                'avatar' => UploadedFile::fake()->image($nombre),
            ])->assertOk();
        }

        $this->assertCount(1, Storage::disk('public')->files('perfiles'));
    }
}
