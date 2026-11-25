import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { WishlistItem } from '../../core/api/models';
import { ReservationsService } from '../../core/api/reservations.service';
import { errorMessage } from '../../core/http/api-error';
import { Notify } from '../../shared/notify';

/**
 * Lo que pasó al reservar: el regalo ya reservado o, si no se pudo, qué decir y si lo que se ve
 * quedó viejo (alguien se adelantó) y hay que volver a pedir la lista.
 */
export type ReserveResult =
  | { ok: true; item: WishlistItem }
  | { ok: false; message: string; stale: boolean };

/**
 * Reservar un regalo de una lista ajena. Lo usan la hoja del regalo (gift-sheet.ts) y el botón
 * «Lo regalo yo» de cada tarjeta (wishlist.page.html), para que digan y hagan lo mismo.
 */
@Injectable({ providedIn: 'root' })
export class ReserveGift {
  private readonly api = inject(ReservationsService);
  private readonly notify = inject(Notify);

  async reserve(item: WishlistItem, ownerName: string): Promise<ReserveResult> {
    try {
      const reservation = await this.api.reserve(item.id);
      await this.notify.toast(`Reservado. ${ownerName} no se va a enterar.`);
      return {
        ok: true,
        item: { ...item, is_reserved: true, reserved_by_me: true, my_reservation_id: reservation.id },
      };
    } catch (error) {
      // 409: otra persona reservó en el mismo instante. 403: ya estaba tomado, recibido o la
      // lista dejó de estar a tu alcance. En los dos casos lo que se ve quedó viejo.
      if (error instanceof HttpErrorResponse && (error.status === 409 || error.status === 403)) {
        return {
          ok: false,
          stale: true,
          message:
            error.status === 409
              ? 'Alguien se te adelantó y ya lo reservó.'
              : 'Ya no se puede reservar. La lista se actualizó con lo que está libre.',
        };
      }
      return {
        ok: false,
        stale: false,
        message: errorMessage(error, 'No se pudo reservar. Intenta de nuevo.'),
      };
    }
  }
}
