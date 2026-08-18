<?php

namespace App\Notifications;

use App\Enums\AccessRequestStatus;
use App\Models\WishlistAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien pidió una lista: el dueño ya respondió.
 *
 * Cierra una promesa que la aplicación ya hacía sin cumplirla: al pedir acceso
 * el mensaje dice «te avisará cuando responda», y hasta ahora no avisaba nada.
 * Quien pedía tenía que volver a entrar a «Solicitudes» a probar suerte.
 */
class AccessAnswered extends Notification implements ShouldQueue
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
        $aprobada = $this->acceso->statusEnum() === AccessRequestStatus::APPROVED;
        $lista = $this->acceso->wishlist;

        return [
            'titulo' => $aprobada
                ? 'Ya puedes ver una lista de '.$lista->user->publicName()
                : $lista->user->publicName().' no te dio acceso',
            // El nombre de la lista solo si te la dieron. Si te la negaron,
            // decirlo sería contar algo de una lista que sigues sin poder ver.
            'detalle' => $aprobada ? $lista->name : 'La lista que le pediste.',
            // Aprobada lleva a la lista; negada, a la pantalla de solicitudes,
            // porque no hay nada que abrir.
            'url' => $aprobada
                ? route('gifts.show', $lista)
                : route('access.index'),
            'icono' => $aprobada ? '🎁' : '🔒',
        ];
    }
}
