<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo se edita el perfil propio: no hay ruta que reciba otro usuario.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => User::usernameRules($this->user()),
            'show_name' => ['boolean'],
            'is_private' => ['boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'quitar_avatar' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::of($this->input('username'))->trim()->ltrim('@')->lower()->toString(),
            // Una casilla sin marcar no se envía: sin esto, desmarcarla no
            // apagaría nada porque el campo simplemente no llegaría.
            'show_name' => $this->boolean('show_name'),
            // La casilla del formulario dice «perfil público», que es lo
            // natural de leer, y acá se invierte: la columna guarda lo
            // contrario. Marcarla apaga is_private.
            'is_private' => ! $this->boolean('perfil_publico'),
            'quitar_avatar' => $this->boolean('quitar_avatar'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'username' => 'usuario',
            'show_name' => 'mostrar el nombre',
            'is_private' => 'privacidad del perfil',
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
