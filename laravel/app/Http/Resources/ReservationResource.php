<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una reserva, vista por quien la hizo.
 *
 * Este recurso solo se sirve al dueño de la reserva —nunca al dueño de la
 * lista—, así que sí lleva el id: es su propia reserva. Lo que no lleva es
 * `user_id`, que en una respuesta al interesado sobra y en cualquier otra
 * sería exactamente la filtración que hay que evitar.
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'note' => $this->note,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'item' => WishlistItemResource::make($this->whenLoaded('wishlistItem')),
        ];
    }
}
