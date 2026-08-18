<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una persona, vista por otra.
 *
 * El correo no sale nunca hacia terceros, y el nombre solo si su dueño lo dejó
 * a la vista: `publicName()` es el único sitio del que debe salir un nombre, y
 * eso vale igual en json que en una vista.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $esElMismo = $this->id === $request->user()?->id;

        return [
            'username' => $this->username,
            'handle' => $this->handle(),
            'display_name' => $this->publicName(),
            'avatar' => $this->avatarSrc(),
            'initials' => $this->initials(),
            // Solo en el propio perfil: a un tercero no le corresponde saber
            // el correo de nadie, ni si oculta su nombre.
            'email' => $this->when($esElMismo, fn () => $this->email),
            'show_name' => $this->when($esElMismo, fn () => (bool) $this->show_name),
            'is_private' => $this->when($esElMismo, fn () => (bool) $this->is_private),
        ];
    }
}
