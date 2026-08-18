<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A quien reservó: tu reserva se soltó porque ya no alcanzas esa lista.
 *
 * Sin este aviso, soltar la reserva sería peor que dejarla: la persona seguiría
 * creyendo que tiene el regalo tomado, iría a comprarlo, y se encontraría con
 * que otro se le adelantó. Que el regalo se libere es lo correcto; que no se
 * diga, no.
 *
 * Nombra el regalo y la lista a propósito: es información que esa persona ya
 * tenía cuando reservó. Lo que no puede es dar acceso otra vez, y no lo da.
 */
class ReservationReleased extends Notification implements ShouldQueue
{
    use Queueable;

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

        return [
            'titulo' => 'Se soltó una reserva tuya',
            'detalle' => $item->displayName().' · ya no puedes ver esa lista',
            'url' => route('reservations.index'),
            'icono' => '🔓',
        ];
    }
}
