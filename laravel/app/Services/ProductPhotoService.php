<?php

namespace App\Services;

use App\Jobs\ShrinkStoredImage;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guardar, cambiar y quitar la foto de una ficha.
 *
 * Un solo lugar por la misma razón que AvatarService: el borrado de la foto
 * anterior es lo que se olvida cuando el guardado vive repartido —al crear el
 * regalo y al cambiarle la foto—, y el disco se llena de fotos que ya nadie
 * muestra.
 */
class ProductPhotoService
{
    private const CARPETA = 'productos';

    /**
     * Guarda la foto tal como llegó, encola el achicado y devuelve su ruta.
     *
     * El nombre lo inventa Laravel: 40 caracteres al azar. Importa porque el
     * disco es público, así que la URL queda adivinable solo para quien la
     * tiene, y de paso el nombre original —que puede traer el nombre de la
     * persona— no termina a la vista.
     */
    public function store(UploadedFile $foto): string
    {
        $ruta = $foto->store(self::CARPETA, 'public');

        // Se guarda tal cual y se achica después: procesar 4 MB en la petición
        // dejaría a quien sube la foto mirando el navegador girar.
        dispatch(ShrinkStoredImage::forProductPhoto($ruta));

        return $ruta;
    }

    /**
     * Cambia la foto de una ficha, borrando la que tenía.
     *
     * Si la ficha traía una foto por URL (image_url), también se va: la subida
     * ya le ganaba al mostrarse, y dejarla guardada haría que reapareciera al
     * quitar la nueva.
     */
    public function replace(Product $product, UploadedFile $foto): void
    {
        $anterior = $product->image_path;

        $product->update([
            'image_path' => $this->store($foto),
            'image_url' => null,
        ]);

        $this->deleteFile($anterior);
    }

    /**
     * Deja la ficha sin foto. Las dos fuentes, por lo mismo que en replace():
     * «quitar la foto» que deja asomar otra no la quitó.
     */
    public function remove(Product $product): void
    {
        $anterior = $product->image_path;

        $product->update(['image_path' => null, 'image_url' => null]);

        $this->deleteFile($anterior);
    }

    private function deleteFile(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
