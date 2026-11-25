<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * El regalo que alguien escribe a mano, cuando no está en el catálogo.
 *
 * Existe porque lo crean dos sitios —el formulario de la web y la API— y son
 * decisiones que no pueden divergir: su autor queda anotado, el nombre del
 * archivo lo inventa Laravel, la foto se achica en la cola, y nace privado
 * salvo que la persona pida compartirlo.
 */
class UserProductCreator
{
    public function __construct(private readonly ProductPhotoService $fotos) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  bool  $alCatalogo  Si la persona pidió compartirlo con todos.
     */
    public function create(
        array $atributos,
        ?UploadedFile $foto,
        User $autor,
        bool $alCatalogo = false,
    ): Product {
        return Product::create([
            ...$atributos,
            'created_by_user_id' => $autor->id,
            // La foto la guarda el mismo servicio que la cambia después.
            'image_path' => $foto ? $this->fotos->store($foto) : null,
            // Privado por defecto: compartir con el catálogo es algo que se
            // pide, nunca lo que pasa por no marcar nada.
            'is_public' => $alCatalogo,
        ]);
    }
}
