<?php

namespace App\Http\Requests;

use App\Enums\ItemPriority;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Agregar un regalo admite dos caminos: elegir algo del catálogo o escribirlo
 * a mano. Uno u otro, nunca los dos.
 */
class StoreWishlistItemRequest extends FormRequest
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
            'product_id' => [
                'nullable',
                'integer',
                // No basta con que exista: tiene que ser algo que este usuario
                // pueda ver, o sería una forma de espiar productos privados
                // ajenos probando ids.
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at')
                        ->where(function ($query) {
                            $query->where('is_public', true)
                                ->orWhere('created_by_user_id', $this->user()->id);
                        });
                }),
            ],

            // Producto escrito a mano: nace privado y solo lo ve su autor.
            'name' => ['required_without:product_id', 'nullable', 'string', 'max:200'],
            'category_id' => ['required_without:product_id', 'nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'url' => ['nullable', 'url', 'max:2048'],
            'reference_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            // 'image' valida que sea una imagen de verdad y no un ejecutable
            // renombrado: mira el contenido, no la extensión. Se acota a los
            // tres formatos que todo navegador muestra —nada de svg, que es
            // xml y puede traer scripts dentro—. 4 MB es lo que pesa una foto
            // de celular sin tocar.
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],

            // Compartir la ficha con el catálogo público. Solo tiene sentido
            // con un producto escrito a mano: si eligió uno del catálogo, ya
            // está publicado.
            'share_with_catalog' => ['boolean'],

            'alias' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
            'priority' => ['required', Rule::in(ItemPriority::labels())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'name' => 'nombre',
            'category_id' => 'categoría',
            'reference_price' => 'precio de referencia',
            'priority' => 'prioridad',
            'notes' => 'notas',
            'image' => 'imagen',
            'share_with_catalog' => 'compartir con el catálogo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Elige algo del catálogo o escribe el nombre del regalo.',
            'category_id.required_without' => 'Elige una categoría para el regalo que escribiste.',
        ];
    }

    public function priority(): ItemPriority
    {
        return ItemPriority::fromLabel($this->string('priority')->toString());
    }

    public function chosenProduct(): ?Product
    {
        return $this->filled('product_id')
            ? Product::findOrFail($this->integer('product_id'))
            : null;
    }
}
