<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una lista de deseos.
 *
 * El `share_token` es la llave de una lista privada, así que sale únicamente
 * para su dueño. Publicarlo en la respuesta que ve un invitado equivaldría a
 * darle permiso para repartir la lista por su cuenta.
 */
class WishlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esMia = $this->user_id === $request->user()?->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'visibility' => $this->visibilityEnum()->label(),
            'event_date' => $this->event_date?->toDateString(),
            'items_count' => $this->when(isset($this->items_count), fn () => $this->items_count),
            'owner' => UserResource::make($this->whenLoaded('user')),
            'is_mine' => $esMia,
            'share_token' => $this->when($esMia, fn () => $this->share_token),
            'items' => WishlistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
