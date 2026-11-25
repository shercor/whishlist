import { Component, computed, inject, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, openOutline, starOutline } from 'ionicons/icons';
import { WishlistItem } from '../../core/api/models';
import { ReservationsService } from '../../core/api/reservations.service';
import { errorMessage } from '../../core/http/api-error';
import { formatPrice } from '../../shared/format';
import { GIFT_STATES, giftStateOf } from '../../shared/gift-state';
import { Notify } from '../../shared/notify';
import { priorityOf } from '../../shared/priority';
import { ReserveGift } from './reserve-gift';

/** Un regalo de una lista ajena: su ficha, reservarlo o soltar la reserva. */
@Component({
  selector: 'app-gift-sheet',
  imports: [RouterLink, IonButton, IonIcon, IonSpinner],
  templateUrl: './gift-sheet.html',
})
export class GiftSheet {
  private readonly api = inject(ReservationsService);
  private readonly notify = inject(Notify);
  private readonly reserveGift = inject(ReserveGift);

  readonly item = input.required<WishlistItem>();
  /** Nombre público del dueño de la lista. */
  readonly ownerName = input.required<string>();
  /** Si se abre fuera de la lista («Voy a regalar»), el id para ofrecer ir a verla. */
  readonly wishlistId = input<number | null>(null);
  readonly changed = output<WishlistItem>();
  /** Lo que se ve quedó viejo (alguien se adelantó): hay que volver a pedir la lista. */
  readonly stale = output<void>();
  readonly closed = output<void>();

  protected readonly state = computed(() => giftStateOf(this.item()));
  protected readonly stateInfo = computed(() => GIFT_STATES[this.state()]);
  protected readonly priority = computed(() => priorityOf(this.item().priority));
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly formatPrice = formatPrice;

  constructor() {
    addIcons({ closeOutline, openOutline, starOutline });
  }

  protected async reserve(): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    const result = await this.reserveGift.reserve(this.item(), this.ownerName());
    this.busy.set(false);
    if (result.ok) {
      this.changed.emit(result.item);
      return;
    }
    this.error.set(result.message);
    if (result.stale) {
      this.stale.emit();
    }
  }

  protected async release(): Promise<void> {
    const id = this.item().my_reservation_id;
    if (!id) {
      return;
    }

    const confirmed = await this.notify.confirm(
      `¿Soltar «${this.item().name}»?`,
      'Queda libre para que lo reserve otra persona.',
      'Soltar',
    );
    if (!confirmed) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      await this.api.release(id);
      this.changed.emit({
        ...this.item(),
        is_reserved: false,
        reserved_by_me: false,
        my_reservation_id: null,
      });
      await this.notify.toast('Reserva soltada.');
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo soltar. Intenta de nuevo.'));
    } finally {
      this.busy.set(false);
    }
  }
}
