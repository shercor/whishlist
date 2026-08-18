<?php

namespace App\Services;

use App\Jobs\ShrinkStoredImage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * El regalo que alguien escribe a mano, cuando no está en el catálogo.
 *
 * Existe porque lo crean dos sitios —el formulario de la web y la API— y son
 * cuatro decisiones que no pueden divergir: nace privado, su autor queda
 * anotado, el nombre del archivo lo inventa Laravel, y la foto se achica en la
 * cola. Con una copia en cada controlador, la segunda se olvida de una.
 */
class PrivateProductCreator
{
    private const CARPETA = 'productos';

    /**
     * @param  array<string, mixed>  $atributos
     */
    public function create(array $atributos, ?UploadedFile $foto, User $autor): Product
    {
        return Product::create([
            ...$atributos,
            'created_by_user_id' => $autor->id,
            'image_path' => $this->guardarFoto($foto),
            // Privado siempre: lo que alguien escribe para su lista no entra
            // al catálogo de nadie más.
            'is_public' => false,
        ]);
    }

    /**
     * Guarda la foto tal como llegó y encola el achicado.
     *
     * El nombre lo inventa Laravel: 40 caracteres al azar. Importa porque el
     * disco es público, así que la URL queda adivinable solo para quien la
     * tiene, y de paso el nombre original —que puede traer el nombre de la
     * persona— no termina a la vista.
     */
    private function guardarFoto(?UploadedFile $foto): ?string
    {
        if (! $foto) {
            return null;
        }

        $ruta = $foto->store(self::CARPETA, 'public');

        // Se guarda tal cual y se achica después: procesar 4 MB en la petición
        // dejaría a quien agrega el regalo mirando el navegador girar.
        dispatch(ShrinkStoredImage::forProductPhoto($ruta));

        return $ruta;
    }
}
