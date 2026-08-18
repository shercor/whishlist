<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un vínculo de seguimiento.
 *
 * Lleva las dos puntas cuando están cargadas, porque el mismo recurso sirve
 * para «quién me sigue» y «a quién sigo», y el cliente necesita saber cuál de
 * los dos es la otra persona.
 */
class FollowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'follower' => UserResource::make($this->whenLoaded('follower')),
            'followed' => UserResource::make($this->whenLoaded('followed')),
        ];
    }
}
