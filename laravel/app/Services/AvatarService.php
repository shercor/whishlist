<?php

namespace App\Services;

use App\Jobs\ShrinkStoredImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guardar y reemplazar la foto de perfil.
 *
 * Existe para tener un solo lugar donde se borre la foto anterior. Repartido
 * entre el registro y el perfil, ese borrado es justo lo que se olvida, y el
 * disco se va llenando de fotos que ya nadie muestra.
 */
class AvatarService
{
    private const CARPETA = 'perfiles';

    /**
     * Guarda la foto y devuelve su ruta, o null si no subieron ninguna.
     *
     * El nombre lo inventa Laravel: 40 caracteres al azar. Así el nombre
     * original del archivo —que suele traer el nombre de la persona— no
     * termina a la vista en la URL.
     */
    public function store(?UploadedFile $foto): ?string
    {
        $ruta = $foto?->store(self::CARPETA, 'public');

        if ($ruta) {
            // La foto queda guardada tal como llegó y la cola la achica en
            // un momento. Mientras tanto se ve igual, solo que pesada.
            dispatch(ShrinkStoredImage::forAvatar($ruta));
        }

        return $ruta;
    }

    /**
     * Cambia la foto de un usuario, borrando la que tenía.
     *
     * Sin foto nueva no hace nada: dejar el campo vacío en el formulario del
     * perfil significa «no la cambio», no «bórrala». Para quitarla está
     * remove().
     */
    public function update(User $user, ?UploadedFile $foto): void
    {
        if (! $foto) {
            return;
        }

        $anterior = $user->avatar_path;

        $user->update(['avatar_path' => $this->store($foto)]);

        $this->deleteFile($anterior);
    }

    public function remove(User $user): void
    {
        $anterior = $user->avatar_path;

        $user->update(['avatar_path' => null]);

        $this->deleteFile($anterior);
    }

    private function deleteFile(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
