<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Los tokens con que se entra a la API.
 *
 * Se modela como un recurso —crear un token es iniciar sesión, borrarlo es
 * cerrarla— en vez de como dos verbos `/login` y `/logout`, que es lo que pide
 * REST y además deja sitio para listar y revocar tokens de un dispositivo
 * concreto sin inventar rutas nuevas.
 */
class TokenController extends Controller
{
    /**
     * El mismo freno que la pantalla de login: cinco intentos por correo e ip.
     * Sin esto la API sería la puerta cómoda para probar contraseñas a lo
     * bruto, justo la que el formulario ya tiene cerrada.
     */
    private const INTENTOS = 5;

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Con qué se identifica el token en la lista de sesiones: «iPhone
            // de Ana». Sin esto todos los tokens se llaman igual y revocar el
            // de un teléfono perdido es adivinar.
            'device_name' => ['required', 'string', 'max:100'],
        ], [], [
            'email' => 'correo',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ]);

        $this->frenarFuerzaBruta($request, $datos['email']);

        if (! Auth::validate(['email' => $datos['email'], 'password' => $datos['password']])) {
            RateLimiter::hit($this->clave($request, $datos['email']));

            throw ValidationException::withMessages([
                'email' => 'Ese correo y esa contraseña no coinciden.',
            ]);
        }

        RateLimiter::clear($this->clave($request, $datos['email']));

        $usuario = Auth::getProvider()->retrieveByCredentials(['email' => $datos['email']]);

        // Se marca como autenticado para esta respuesta. No abre sesión: es lo
        // que permite que UserResource reconozca que el perfil es el propio y
        // devuelva el correo. Sin esto, quien acaba de entrar recibía su propia
        // ficha recortada como si fuera un desconocido.
        Auth::setUser($usuario);

        return response()->json([
            'token' => $usuario->createToken($datos['device_name'])->plainTextToken,
            'user' => UserResource::make($usuario),
        ], 201);
    }

    /**
     * Cerrar la sesión de este dispositivo: se revoca el token con el que
     * llegó la petición y no los demás, que siguen siendo válidos.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    /**
     * Cerrar sesión en todas partes. Es lo que se aprieta cuando se pierde un
     * teléfono.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(status: 204);
    }

    private function frenarFuerzaBruta(Request $request, string $email): void
    {
        $clave = $this->clave($request, $email);

        if (! RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos. Prueba de nuevo en '
                .RateLimiter::availableIn($clave).' segundos.',
        ]);
    }

    private function clave(Request $request, string $email): string
    {
        return 'api-token|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }
}
