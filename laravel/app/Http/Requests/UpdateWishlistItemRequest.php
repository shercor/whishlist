<?php

namespace App\Http\Requests;

use App\Enums\ItemPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El producto no se cambia: si el regalo es otro, se borra el ítem y se
     * agrega el nuevo. Cambiarlo por debajo confundiría a quien ya lo reservó.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alias' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
            'priority' => ['required', Rule::in(ItemPriority::labels())],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'priority' => 'prioridad',
            'notes' => 'notas',
            'position' => 'posición',
        ];
    }
}
