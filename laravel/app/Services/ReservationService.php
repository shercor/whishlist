<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\ReservationExpiring;
use App\Notifications\ReservationReleased;
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

        $porAvisar = Reservation::expiringSoon(self::DIAS_DE_AVISO)
            // Sin el regalo no hay nada que decir: el aviso nombra el regalo y
            // a quién es, así que sobre una reserva huérfana la notificación
            // revienta contra un nulo. Y lo hace **dentro del worker**, donde
            // no lo ve nadie: la petición que la encoló termina bien.
            ->whereHas('wishlistItem.wishlist')
            ->with('user');

        foreach ($porAvisar->cursor() as $reservation) {
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
     * Suelta las reservas de quien ya no puede ver la lista donde está el
     * regalo. Devuelve cuántas soltó.
     *
     * **Por qué barre en vez de engancharse a cada acción.** Se pierde el
     * acceso por diez caminos —dejar de seguir, que te echen de seguidores,
     * rechazar un seguimiento, revocar un acceso, cada uno por web y por API—
     * y por dos más que no tocan ninguna fila de seguimiento ni de acceso: que
     * el dueño vuelva privada una lista pública, o que cierre su perfil.
     * Enganchar diez sitios es cómo se olvida el once.
     *
     * Pregunta a la misma policy que decide si la lista se abre, así que la
     * regla vive en un solo lugar y sigue valiendo cuando la policy cambie.
     * Una reserva viva es un puñado de filas: preguntar por cada una sale
     * barato, igual que en `visibleWishlistsFor`.
     */
    public function releaseUnreachable(): int
    {
        $soltadas = 0;

        $vivas = Reservation::query()
            ->whereNotNull('active_flag')
            ->whereHas('wishlistItem.wishlist')
            ->with(['user', 'wishlistItem.wishlist.user'])
            ->cursor();

        foreach ($vivas as $reservation) {
            if ($reservation->user->can('view', $reservation->wishlistItem->wishlist)) {
                continue;
            }

            $reservation->release(ReservationStatus::CANCELLED);

            // Soltarla sin decirlo sería peor que dejarla: la persona iría a
            // comprar un regalo que ya no tiene tomado.
            $reservation->user->notify(new ReservationReleased($reservation));

            $soltadas++;
        }

        return $soltadas;
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
