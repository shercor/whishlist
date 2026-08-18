<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\ReservationExpiring;
use Illuminate\Database\QueryException;

/**
 * Reservar y soltar regalos. Existe para tener un solo lugar donde se maneje
 * el choque de dos personas reservando a la vez: la policy mira si el ítem
 * está libre, pero entre esa mirada y el insert cabe otra reserva. Quien
 * decide es la base, y acá se traduce su error a algo que se le pueda decir
 * al usuario.
 */
class ReservationService
{
    /**
     * Días que dura una reserva antes de que el job la libere. Si nadie
     * compra en dos semanas, el regalo vuelve a estar disponible.
     */
    public const DIAS_DE_PLAZO = 14;

    /**
     * Devuelve la reserva creada, o null si alguien se adelantó.
     */
    public function reserve(WishlistItem $item, User $user, ?string $note = null): ?Reservation
    {
        try {
            return Reservation::create([
                'wishlist_item_id' => $item->id,
                'user_id' => $user->id,
                'status' => ReservationStatus::ACTIVE->label(),
                'active_flag' => 1,
                'expires_at' => now()->addDays(self::DIAS_DE_PLAZO),
                'note' => $note,
            ]);
        } catch (QueryException $e) {
            // 23000 acá solo puede ser el índice único de reserva activa: se
            // adelantó otra persona por milisegundos.
            if ($e->getCode() === '23000') {
                return null;
            }

            throw $e;
        }
    }

    public function release(Reservation $reservation, ReservationStatus $status = ReservationStatus::CANCELLED): void
    {
        $reservation->release($status);
    }

    /**
     * Días de antelación con que se avisa a quien reservó.
     *
     * Tres deja margen para ir a comprar el fin de semana sin que el aviso
     * llegue tan pronto que se olvide.
     */
    public const DIAS_DE_AVISO = 3;

    /**
     * Avisa a quien reservó que su plazo está por vencer, una sola vez por
     * reserva. Devuelve cuántos avisos salieron.
     */
    public function warnExpiring(): int
    {
        $avisadas = 0;

        foreach (Reservation::expiringSoon(self::DIAS_DE_AVISO)->with('user')->cursor() as $reservation) {
            $reservation->user->notify(new ReservationExpiring($reservation));

            // Se marca aunque la notificación se encole: si el worker falla,
            // el aviso se pierde, pero repetirlo cada día sería peor. La
            // notificación fallida queda en `failed_jobs`.
            $reservation->update(['expiry_warned_at' => now()]);

            $avisadas++;
        }

        return $avisadas;
    }

    /**
     * Libera las reservas cuyo plazo venció. Lo usa el comando programado.
     */
    public function releaseExpired(): int
    {
        $liberadas = 0;

        foreach (Reservation::expired()->cursor() as $reservation) {
            $reservation->release(ReservationStatus::EXPIRED);
            $liberadas++;
        }

        return $liberadas;
    }
}
