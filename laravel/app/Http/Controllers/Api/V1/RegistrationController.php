<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Crear una cuenta. Responde igual que `POST /tokens`: quien se registra queda
 * dentro, como en la web, sin verificar el correo.
 */
class RegistrationController extends Controller
{
    public function store(RegisterUserRequest $request, AvatarService $avatares): JsonResponse
    {
        $usuario = User::create([
            ...$request->only('name', 'username', 'email', 'password'),
            'avatar_path' => $avatares->store($request->file('avatar')),
        ]);

        event(new Registered($usuario));

        // Para que UserResource lo reconozca como el perfil propio y traiga el correo.
        Auth::setUser($usuario);

        return response()->json([
            'token' => $usuario->createToken($request->validated('device_name'))->plainTextToken,
            'user' => UserResource::make($usuario),
        ], 201);
    }
}
