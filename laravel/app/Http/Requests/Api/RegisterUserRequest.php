<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Auth\RegisterRequest;

/**
 * Registrarse desde la app: lo mismo que el formulario de la web, más el nombre
 * del dispositivo para el token con que queda dentro.
 */
class RegisterUserRequest extends RegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'device_name' => 'nombre del dispositivo',
        ];
    }
}
