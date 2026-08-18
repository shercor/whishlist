<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Achica una imagen ya guardada, sobre su propio archivo.
 *
 * Una foto de celular son 4000 px de ancho y 4 MB; la más grande que esta
 * aplicación dibuja mide 320. Sin este job, el navegador se baja los 4 MB
 * enteros para pintar una miniatura y tira el 99% de los píxeles.
 *
 * Reescribe el mismo archivo a propósito: la ruta ya está guardada en la fila
 * y publicada en el HTML, así que cambiarle el nombre obligaría a actualizar
 * la base desde la cola y a convivir con dos versiones mientras tanto. El
 * formato tampoco cambia —un png sigue siendo png— porque la extensión es
 * parte de esa ruta.
 *
 * Es idempotente: `scaleDown` nunca agranda, así que volver a correrlo sobre
 * una imagen ya achicada no la degrada. Importa porque la cola puede repetir
 * un job que se cayó después de haber hecho su trabajo.
 */
class ShrinkStoredImage implements ShouldQueue
{
    use Queueable;

    /**
     * Lado máximo de una foto de perfil.
     *
     * El avatar más grande de la aplicación mide 80 px (`.avatar-grande`).
     * 320 es cuatro veces eso: cubre pantallas retina y deja margen para que
     * el diseño crezca sin tener que volver a procesar lo ya subido.
     */
    public const LADO_AVATAR = 320;

    /**
     * Lado máximo de la foto de un regalo.
     *
     * Se ve a 72 px en las tarjetas y hasta 320 en el modal de detalle. 800
     * da margen de sobra para mirar bien el regalo, que es de lo que se trata
     * ese modal.
     */
    public const LADO_PRODUCTO = 800;

    /**
     * Tres intentos: un fallo acá suele ser el disco ocupado o la imagen a
     * medio escribir, y las dos cosas se arreglan solas al reintentar. Lo que
     * no se arregla —un archivo corrupto— acaba en `failed_jobs`, que es
     * donde hay que ir a mirar si una foto quedó pesada.
     */
    public int $tries = 3;

    public function __construct(
        private readonly string $disk,
        private readonly string $path,
        private readonly int $ladoMaximo,
    ) {}

    public static function forAvatar(string $path, string $disk = 'public'): self
    {
        return new self($disk, $path, self::LADO_AVATAR);
    }

    public static function forProductPhoto(string $path, string $disk = 'public'): self
    {
        return new self($disk, $path, self::LADO_PRODUCTO);
    }

    public function handle(): void
    {
        $almacen = Storage::disk($this->disk);

        // El archivo pudo desaparecer entre que se encoló y llegó el turno:
        // alguien cambió su foto dos veces seguidas, o borró el regalo. No es
        // un error, ya no hay nada que achicar.
        if (! $almacen->exists($this->path)) {
            return;
        }

        $imagen = ImageManager::usingDriver(Driver::class)
            ->decodeBinary($almacen->get($this->path))
            ->scaleDown(width: $this->ladoMaximo, height: $this->ladoMaximo);

        // encodeUsingPath elige el codificador por la extensión, así que el
        // formato se conserva sin tener que preguntarlo aparte.
        $almacen->put($this->path, (string) $imagen->encodeUsingPath($this->path, quality: 82));
    }
}
