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
            ->with(['user', 'wishlistItem.wishlist.user']);

        foreach ($porAvisar->lazy() as $reservation) {
            // Quien ya no alcanza la lista no tiene nada que hacer con un «te
            // quedan 3 días»: el barrido de fuera de alcance está a punto de
            // soltarle la reserva. Antes esto dependía de que el barrido
            // corriera primero, y los dos comandos están programados a la
            // misma hora —el orden lo decidía el orden de registro en
            // `routes/console.php`, que es un hilo muy fino del que colgar un
            // mensaje equivocado—.
            if (! $reservation->user->can('viewDurably', $reservation->wishlistItem->wishlist)) {
                continue;
            }

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

        // Una misma persona suele tener varios regalos reservados de la misma
        // lista. La respuesta de la policy es la misma para todos, y cuesta
        // dos consultas cada vez: se pregunta una y se recuerda.
        $veredictos = [];

        $vivas = Reservation::query()
            ->whereNotNull('active_flag')
            // `withTrashed` a propósito: antes esto era un `whereHas` que
            // **descartaba** las reservas colgando de un regalo o una lista
            // borrados, que es justo al revés de lo que hace falta. Los hooks
            // de borrado las sueltan, pero un borrado que no pase por el
            // modelo —un update masivo, SQL a mano— deja la reserva viva
            // bloqueando un ítem que ya nadie puede ver, y ningún barrido
            // volvía a mirarla. Hizo falta una migración de una vez
            // (`release_orphaned_reservations`) para limpiar las que ya había;
            // esto evita necesitar la siguiente.
            ->with([
                'user',
                'wishlistItem' => fn ($query) => $query->withTrashed(),
                'wishlistItem.wishlist' => fn ($query) => $query->withTrashed(),
                'wishlistItem.wishlist.user',
            ])
            // `lazy()` y no `cursor()`: cursor() ignora el `with()` de arriba y
            // vuelve a la base por cada relación de cada reserva. Se veía como
            // seis consultas por reserva, en un comando que corre cada hora.
            ->lazy();

        foreach ($vivas as $reservation) {
            $item = $reservation->wishlistItem;
            $wishlist = $item?->wishlist;

            $huerfana = ! $reservation->user
                || ! $item || $item->trashed()
                || ! $wishlist || $wishlist->trashed();

            if ($huerfana) {
                // En silencio, igual que los hooks de borrado: el aviso nombra
                // el regalo y a quién es, y aquí no hay ninguna de las dos
                // cosas que nombrar.
                $reservation->release(ReservationStatus::CANCELLED);
                $soltadas++;

                continue;
            }

            $clave = $reservation->user_id.':'.$wishlist->id;

            // Nunca `can('view', ...)`: eso mira la sesión de quien esté
            // corriendo esto y la aplicaría a una persona que no es. Ver
            // `WishlistPolicy::viewDurably()`.
            $alcanza = $veredictos[$clave] ??= $reservation->user->can('viewDurably', $wishlist);

            if ($alcanza) {
                continue;
            }

            $reservation->release(ReservationStatus::REVOKED);

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
