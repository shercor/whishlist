<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->data['titulo'] ?? null,
            'detail' => $this->data['detalle'] ?? null,
            'url' => $this->data['url'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
