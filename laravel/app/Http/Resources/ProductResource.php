<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una ficha del catálogo.
 *
 * `created_by_user_id` no sale nunca: quién dio de alta un producto privado no
 * le importa a nadie más, y en el catálogo público es ruido.
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'image' => $this->imageSrc(),
            'reference_price' => $this->reference_price,
            'currency' => $this->currency,
            'is_public' => (bool) $this->is_public,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'likes' => $this->when(isset($this->likes_count), fn () => $this->likes_count),
            'liked_by_me' => $this->when(
                isset($this->mine_likes_count),
                fn () => $this->mine_likes_count > 0
            ),
        ];
    }
}
