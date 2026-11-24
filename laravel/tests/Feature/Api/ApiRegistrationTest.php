<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registrarse desde la app: `POST /users` crea la cuenta y deja dentro.
 */
class ApiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'name' => 'Tía Marta',
            'username' => '@Marta_T',
            'email' => 'marta@ejemplo.test',
            'password' => 'una-clave-larga',
            'password_confirmation' => 'una-clave-larga',
            'device_name' => 'Navegador web',
            ...$cambios,
        ];
    }

    public function test_registering_creates_the_account_and_leaves_you_inside(): void
    {
        $respuesta = $this->postJson(route('api.v1.users.store'), $this->datos())
            ->assertCreated()
            ->assertJsonPath('user.username', 'marta_t')
            ->assertJsonPath('user.email', 'marta@ejemplo.test');

        $this->assertDatabaseHas('users', ['username' => 'marta_t', 'email' => 'marta@ejemplo.test']);

        // El token sirve para seguir: es la sesión de la app.
        $this->withToken($respuesta->json('token'))
            ->getJson(route('api.v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.username', 'marta_t');
    }

    public function test_a_taken_username_or_email_and_a_mistyped_password_are_rejected(): void
    {
        User::factory()->create(['username' => 'marta_t', 'email' => 'marta@ejemplo.test']);

        $this->postJson(route('api.v1.users.store'), $this->datos(['password_confirmation' => 'otra-cosa']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email', 'password'])
            ->assertJsonPath('errors.username.0', 'Ese usuario ya está tomado. Prueba con otro.');

        $this->assertSame(1, User::count());
    }
}
