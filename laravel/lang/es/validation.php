<?php

/*
 * Mensajes de validación en español. Solo están las reglas que la aplicación
 * usa de verdad; para el resto cae al inglés de Laravel (fallback_locale).
 * El nombre legible de cada campo lo pone attributes() en cada FormRequest.
 */

return [
    'required' => 'El campo :attribute es obligatorio.',
    'required_without' => 'El campo :attribute es obligatorio cuando no hay :values.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'url' => 'El campo :attribute debe ser una dirección web válida.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'in' => 'El valor de :attribute no es válido.',
    'exists' => 'El :attribute seleccionado no existe o no puedes usarlo.',
    'unique' => 'Ese :attribute ya está registrado.',

    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
    ],

    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
    ],

    'password' => [
        'letters' => 'La contraseña debe tener al menos una letra.',
        'mixed' => 'La contraseña debe tener mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe tener al menos un número.',
        'symbols' => 'La contraseña debe tener al menos un símbolo.',
        'uncompromised' => 'Esa contraseña apareció en una filtración de datos. Elige otra.',
    ],
];
