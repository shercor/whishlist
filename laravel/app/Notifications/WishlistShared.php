<?php

namespace App\Notifications;

use App\Models\WishlistAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien recibe una lista privada sin haberla pedido.
 *
 * Es el camino previsto para repartir una lista privada —el dueño elige a quién
 * se la da— y sin aviso era completamente invisible: la persona tenía acceso a
 * algo que no sabía que existía, así que nunca lo abría.
 */
class WishlistShared extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Si lo que se iba a anunciar ya no existe, el aviso se descarta en vez de
     * fallar.
     *
     * La cola guarda una referencia al modelo y lo vuelve a leer al ejecutar,
     * así que entre encolar y ejecutar cabe que alguien deshaga el seguimiento,
     * borre la lista o suelte la reserva. Sin esto el job muere **dentro del
     * worker**, donde el fallo no lo ve nadie hasta mirar `failed_jobs`.
     * Comprobado: pasaba de verdad.
     *
     * Descartarlo es lo correcto: no hay nada que anunciar.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly WishlistAccess $acceso) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $lista = $this->acceso->wishlist;

        return [
            'titulo' => $lista->user->publicName().' te compartió una lista',
            'detalle' => $lista->name,
            'url' => route('gifts.show', $lista),
            'icono' => '🎁',
        ];
    }
}
