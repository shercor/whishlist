<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => User::usernameRules(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Mismo criterio que la foto de un producto: se valida por
            // contenido y no por extensión, y sin svg, que es xml y puede
            // traer scripts dentro.
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * Escribir «@Ana» o «Ana» en el campo es lo natural, y las dos cosas
     * significan lo mismo. Se normaliza antes de validar en vez de rechazarlo.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => Str::of($this->input('username'))->trim()->ltrim('@')->lower()->toString(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'username' => 'usuario',
            'email' => 'correo',
            'password' => 'contraseña',
            'avatar' => 'foto de perfil',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'El usuario empieza con una letra y sigue con letras, números o guion bajo. Sin espacios ni tildes.',
            'username.unique' => 'Ese usuario ya está tomado. Prueba con otro.',
            'username.not_in' => 'Ese usuario está reservado por el sistema. Prueba con otro.',
        ];
    }
}
