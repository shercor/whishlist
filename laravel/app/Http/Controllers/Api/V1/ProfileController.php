<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * El perfil propio.
 *
 * No reutiliza el `ProfileRequest` de la web a propósito: ese invierte una
 * casilla llamada «perfil_publico» para obtener `is_private`, que es lenguaje
 * de formulario. Un cliente de API manda el campo que existe.
 *
 * La foto de perfil no se cambia por acá, sino en `PUT me/avatar`: es lo único
 * que viaja como multipart, y quitarla necesita un verbo propio porque en un
 * PATCH «no mando el campo» y «bórralo» se confunden. El porqué completo está
 * en AvatarController.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user())->resolve($request),
        ]);
    }

    /**
     * `sometimes` en todos los campos: un PATCH cambia lo que trae y deja el
     * resto en paz, que es la diferencia con un PUT.
     */
    public function update(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // El arroba y las mayúsculas se perdonan igual que en el formulario:
        // un cliente que manda «@Ana» quiere decir «ana», y rechazarlo sería
        // pedante sin ganar nada.
        if ($request->has('username')) {
            $request->merge([
                'username' => Str::of($request->input('username'))
                    ->trim()->ltrim('@')->lower()->toString(),
            ]);
        }

        $datos = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', ...User::usernameRules($usuario)],
            'show_name' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
        ], [
            'username.regex' => 'El usuario empieza con una letra y sigue con letras, números o guion bajo. Sin espacios ni tildes.',
            'username.unique' => 'Ese usuario ya está tomado. Prueba con otro.',
            'username.not_in' => 'Ese usuario está reservado por el sistema. Prueba con otro.',
        ], [
            'name' => 'nombre',
            'username' => 'usuario',
        ]);

        $usuario->update($datos);

        return response()->json([
            'data' => UserResource::make($usuario->fresh())->resolve($request),
        ]);
    }
}
