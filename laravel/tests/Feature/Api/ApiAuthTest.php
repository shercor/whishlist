<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Entrar y salir de la API.
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::factory()->create([
            'email' => 'ana@ejemplo.cl',
            'password' => Hash::make('secreta123'),
        ]);
    }

    public function test_correct_credentials_return_a_token(): void
    {
        $this->usuario();

        $respuesta = $this->postJson(route('api.v1.tokens.store'), [
            'email' => 'ana@ejemplo.cl',
            'password' => 'secreta123',
            'device_name' => 'iPhone de Ana',
        ])->assertCreated();

        $this->assertNotEmpty($respuesta->json('token'));
        $this->assertSame('ana@ejemplo.cl', $respuesta->json('user.email'));
    }

    public function test_a_wrong_password_gives_a_validation_error_and_no_token(): void
    {
        $this->usuario();

        $this->postJson(route('api.v1.tokens.store'), [
            'email' => 'ana@ejemplo.cl',
            'password' => 'la-que-no-es',
            'device_name' => 'iPhone',
        ])->assertStatus(422)->assertJsonMissingPath('token');
    }

    /**
     * El mismo freno que el formulario. Sin esto la API sería la puerta cómoda
     * para probar contraseñas a lo bruto.
     */
    public function test_repeated_failures_get_throttled(): void
    {
        $this->usuario();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.v1.tokens.store'), [
                'email' => 'ana@ejemplo.cl',
                'password' => 'mal',
                'device_name' => 'X',
            ]);
        }

        $respuesta = $this->postJson(route('api.v1.tokens.store'), [
            'email' => 'ana@ejemplo.cl',
            // Incluso con la correcta: el freno ya bajó.
            'password' => 'secreta123',
            'device_name' => 'X',
        ])->assertStatus(422);

        $this->assertStringContainsString('Demasiados intentos', $respuesta->json('errors.email.0'));
    }

    public function test_without_a_token_everything_answers_401(): void
    {
        foreach ([
            route('api.v1.me.show'),
            route('api.v1.wishlists.index'),
            route('api.v1.reservations.index'),
            route('api.v1.products.index'),
            route('api.v1.notifications.index'),
            route('api.v1.follows.index'),
            route('api.v1.access.index'),
        ] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
    }

    /**
     * Un 401 y no una redirección al login: un cliente de API sabe leer el
     * código, no sabe qué hacer con un html de inicio de sesión.
     */
    public function test_the_answer_is_json_and_not_a_redirect(): void
    {
        $respuesta = $this->getJson(route('api.v1.me.show'));

        $respuesta->assertUnauthorized();
        $this->assertJson($respuesta->content());
    }

    public function test_closing_the_session_revokes_only_this_device(): void
    {
        $usuario = $this->usuario();

        $esteAparato = $usuario->createToken('telefono');
        $usuario->createToken('tablet');

        $this->withToken($esteAparato->plainTextToken)
            ->deleteJson(route('api.v1.tokens.destroy'))
            ->assertNoContent();

        // Queda el de la tablet: cerrar sesión en un aparato no echa al otro.
        $this->assertSame(1, $usuario->fresh()->tokens()->count());
    }

    public function test_closing_everywhere_revokes_every_token(): void
    {
        $usuario = $this->usuario();
        $token = $usuario->createToken('telefono');
        $usuario->createToken('tablet');

        $this->withToken($token->plainTextToken)
            ->deleteJson(route('api.v1.tokens.destroy-all'))
            ->assertNoContent();

        $this->assertSame(0, $usuario->fresh()->tokens()->count());
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $usuario = $this->usuario();
        $token = $usuario->createToken('telefono');

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me.show'))->assertOk();

        $usuario->tokens()->delete();

        // Sin esto el test miente: entre dos peticiones del mismo test el
        // guard se queda con el usuario que ya resolvió, así que el segundo
        // getJson pasaría sin volver a mirar el token. En producción cada
        // petición es un proceso nuevo y no existe esa caché.
        $this->app['auth']->forgetGuards();

        $this->withToken($token->plainTextToken)->getJson(route('api.v1.me.show'))->assertUnauthorized();
    }

    public function test_my_profile_carries_my_email_but_a_stranger_profile_does_not(): void
    {
        $yo = $this->usuario();
        $otra = User::factory()->create(['email' => 'otra@ejemplo.cl', 'name' => 'Bruno Díaz']);

        Sanctum::actingAs($yo);

        $this->getJson(route('api.v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@ejemplo.cl');

        $perfilAjeno = $this->getJson(route('api.v1.users.show', $otra))->assertOk();

        $this->assertArrayNotHasKey('email', $perfilAjeno->json('data'));
        $this->assertStringNotContainsString('otra@ejemplo.cl', $perfilAjeno->content());
    }
}
