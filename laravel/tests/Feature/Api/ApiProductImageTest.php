<?php

namespace Tests\Feature\Api;

use App\Jobs\ShrinkStoredImage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cambiar y quitar la foto de una ficha propia por la API.
 *
 * Lo que importa: que solo el autor pueda (ni otro usuario, ni nadie sobre el
 * catálogo curado), que la foto anterior no quede huérfana en el disco, que la
 * respuesta traiga la URL nueva y que la foto nueva pase por el achicado.
 */
class ApiProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_author_uploads_a_photo_and_gets_its_url(): void
    {
        Storage::fake('public');
        Queue::fake();
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create();
        Sanctum::actingAs($autor);

        $respuesta = $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('mia.jpg'),
        ])->assertOk();

        $ruta = $ficha->fresh()->image_path;

        $this->assertNotNull($ruta);
        Storage::disk('public')->assertExists($ruta);
        $this->assertStringContainsString($ruta, $respuesta->json('data.image'));
        $this->assertTrue($respuesta->json('data.is_mine'));
        Queue::assertPushed(ShrinkStoredImage::class);
    }

    public function test_a_new_photo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create();
        Sanctum::actingAs($autor);

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('primera.jpg'),
        ])->assertOk();
        $primera = $ficha->fresh()->image_path;

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('segunda.jpg'),
        ])->assertOk();
        $segunda = $ficha->fresh()->image_path;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertExists($segunda);
        Storage::disk('public')->assertMissing($primera);
    }

    public function test_a_new_photo_replaces_one_given_by_url(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create([
            'image_url' => 'https://example.com/vieja.jpg',
        ]);
        Sanctum::actingAs($autor);

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('nueva.jpg'),
        ])->assertOk();

        // Si quedara guardada, reaparecería al quitar la foto nueva.
        $this->assertNull($ficha->fresh()->image_url);
    }

    public function test_removing_the_photo_clears_both_sources_and_the_file(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create([
            'image_url' => 'https://example.com/vieja.jpg',
        ]);
        Sanctum::actingAs($autor);

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('unica.jpg'),
        ])->assertOk();
        $ruta = $ficha->fresh()->image_path;

        $this->delete(route('api.v1.products.image.destroy', $ficha))
            ->assertOk()
            ->assertJsonPath('data.image', null);

        $this->assertNull($ficha->fresh()->image_path);
        $this->assertNull($ficha->fresh()->image_url);
        Storage::disk('public')->assertMissing($ruta);
    }

    public function test_removing_a_photo_that_was_not_there_is_not_an_error(): void
    {
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create();
        Sanctum::actingAs($autor);

        $this->delete(route('api.v1.products.image.destroy', $ficha))
            ->assertOk()
            ->assertJsonPath('data.image', null);
    }

    public function test_the_author_can_change_it_even_when_the_card_is_public(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create(['is_public' => true]);
        Sanctum::actingAs($autor);

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('nueva.jpg'),
        ])->assertOk();
    }

    public function test_someone_else_cannot_change_or_remove_it(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create(['is_public' => true]);
        Sanctum::actingAs(User::factory()->create());

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->image('ajena.jpg'),
        ])->assertForbidden();
        $this->delete(route('api.v1.products.image.destroy', $ficha))->assertForbidden();

        $this->assertNull($ficha->fresh()->image_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_nobody_changes_the_curated_catalog(): void
    {
        $curada = Product::factory()->create(['image_url' => 'https://example.com/foto.jpg']);
        Sanctum::actingAs(User::factory()->create());

        $this->put(route('api.v1.products.image.store', $curada), [
            'image' => UploadedFile::fake()->image('otra.jpg'),
        ])->assertForbidden();
        $this->delete(route('api.v1.products.image.destroy', $curada))->assertForbidden();

        $this->assertSame('https://example.com/foto.jpg', $curada->fresh()->image_url);
    }

    public function test_it_only_takes_real_images(): void
    {
        Storage::fake('public');
        $autor = User::factory()->create();
        $ficha = Product::factory()->privateFor($autor)->create();
        Sanctum::actingAs($autor);

        $this->put(route('api.v1.products.image.store', $ficha), [
            'image' => UploadedFile::fake()->create('foto.png', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->put(route('api.v1.products.image.store', $ficha), [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_it_needs_a_token(): void
    {
        $ficha = Product::factory()->create();

        $this->putJson(route('api.v1.products.image.store', $ficha))->assertUnauthorized();
        $this->deleteJson(route('api.v1.products.image.destroy', $ficha))->assertUnauthorized();
    }
}
