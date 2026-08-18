<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Que la foto vieja se borre del disco al cambiarla.
 *
 * Es la clase de error que no da la cara: la aplicación sigue funcionando, la
 * foto nueva se ve bien, y lo único que pasa es que el disco se va llenando de
 * archivos que ya nadie muestra. Sin este test, se descubre el día que no
 * queda espacio.
 */
class AvatarStorageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El formulario del perfil manda siempre todos sus campos: si faltara
     * alguno, la validación devolvería un error y el test pasaría por la razón
     * equivocada —sin llegar nunca a tocar el disco—.
     */
    private function datosDePerfil(User $usuario, array $extra = []): array
    {
        return [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'perfil_publico' => '1',
            ...$extra,
        ];
    }

    public function test_replacing_the_profile_photo_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'avatar' => UploadedFile::fake()->image('primera.jpg'),
            ]));

        $primera = $usuario->fresh()->avatar_path;
        $this->assertNotNull($primera);
        Storage::disk('public')->assertExists($primera);

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'avatar' => UploadedFile::fake()->image('segunda.jpg'),
            ]));

        $segunda = $usuario->fresh()->avatar_path;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertExists($segunda);
        // Lo que importa: la primera ya no ocupa lugar.
        Storage::disk('public')->assertMissing($primera);
    }

    public function test_removing_the_photo_deletes_the_file_and_leaves_the_field_empty(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'avatar' => UploadedFile::fake()->image('unica.jpg'),
            ]));

        $ruta = $usuario->fresh()->avatar_path;

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'quitar_avatar' => '1',
            ]));

        $this->assertNull($usuario->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($ruta);
    }

    /**
     * Dejar el campo vacío significa «no la cambio», no «bórrala». Si esto se
     * rompiera, cualquiera que editara su nombre perdería la foto.
     */
    public function test_saving_the_profile_without_a_new_photo_keeps_the_old_one(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'avatar' => UploadedFile::fake()->image('la_mia.jpg'),
            ]));

        $ruta = $usuario->fresh()->avatar_path;

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'name' => 'Otro Nombre',
            ]));

        $this->assertSame($ruta, $usuario->fresh()->avatar_path);
        Storage::disk('public')->assertExists($ruta);
    }

    /**
     * El nombre del archivo lo inventa Laravel. Importa porque el original
     * suele traer el nombre de la persona, y la ruta termina a la vista en el
     * HTML de todas las páginas: sería una forma de filtrar el nombre de quien
     * eligió ocultarlo.
     */
    public function test_the_stored_file_does_not_keep_the_original_name(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->patch(route('profile.update'), $this->datosDePerfil($usuario, [
                'avatar' => UploadedFile::fake()->image('ana-rojas-cumpleanios.jpg'),
            ]));

        $ruta = $usuario->fresh()->avatar_path;

        $this->assertStringStartsWith('perfiles/', $ruta);
        $this->assertStringNotContainsString('ana-rojas', $ruta);
    }

    /**
     * Cada persona borra lo suyo. Con nombres al azar no debería poder pasar
     * otra cosa, pero es justo lo que un refactor del servicio podría romper
     * sin que nada más se queje.
     */
    public function test_changing_your_photo_does_not_touch_someone_elses(): void
    {
        Storage::fake('public');

        $ana = User::factory()->create();
        $bruno = User::factory()->create();

        $this->actingAs($bruno)
            ->patch(route('profile.update'), $this->datosDePerfil($bruno, [
                'avatar' => UploadedFile::fake()->image('bruno.jpg'),
            ]));

        $laDeBruno = $bruno->fresh()->avatar_path;

        $this->actingAs($ana)
            ->patch(route('profile.update'), $this->datosDePerfil($ana, [
                'avatar' => UploadedFile::fake()->image('ana.jpg'),
            ]));
        $this->actingAs($ana)
            ->patch(route('profile.update'), $this->datosDePerfil($ana, [
                'avatar' => UploadedFile::fake()->image('ana2.jpg'),
            ]));

        Storage::disk('public')->assertExists($laDeBruno);
    }
}
