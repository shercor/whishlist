<?php

namespace App\Http\Requests;

use App\Enums\WishlistVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WishlistRequest extends FormRequest
{
    /**
     * Quién puede crear o editar lo decide la policy en el controlador.
     */
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
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', Rule::in(WishlistVisibility::labels())],
            'event_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'visibility' => 'visibilidad',
            'event_date' => 'fecha del evento',
        ];
    }

    public function visibility(): WishlistVisibility
    {
        return WishlistVisibility::fromLabel($this->string('visibility')->toString());
    }
}
