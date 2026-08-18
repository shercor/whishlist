<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistAccessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'message' => $this->message,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
            'wishlist' => WishlistResource::make($this->whenLoaded('wishlist')),
        ];
    }
}
