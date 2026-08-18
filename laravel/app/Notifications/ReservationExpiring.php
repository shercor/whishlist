<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien reservó: tu plazo está por vencer.
 *
 * Es la notificación que existe para reparar un daño concreto. El job que
 * libera reservas vencidas suelta el regalo a los 14 días sin decir nada, así
 * que quien pensaba comprarlo se enteraba al volver y encontrarlo tomado por
 * otro. Este aviso llega antes de que eso ocurra.
 *
 * Va únicamente a quien reservó. El dueño de la lista no se entera de nada:
 * saber que su regalo está reservado, y por quién, es exactamente la sorpresa
 * que la aplicación protege.
 */
class ReservationExpiring extends Notification implements ShouldQueue
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

    public function __construct(private readonly Reservation $reserva) {}

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
        $item = $this->reserva->wishlistItem;
        $dias = max(0, (int) now()->startOfDay()->diffInDays($this->reserva->expires_at->startOfDay(), false));

        return [
            'titulo' => $dias === 0
                ? 'Tu reserva vence hoy'
                : ($dias === 1 ? 'Tu reserva vence mañana' : "Tu reserva vence en {$dias} días"),
            // displayName() respeta el alias que le puso el dueño, que es como
            // el regalo se llama en la lista donde lo viste.
            'detalle' => $item->displayName().' · para '.$item->wishlist->user->publicName(),
            'url' => route('reservations.index'),
            'icono' => '⏳',
        ];
    }
}
