import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  InfiniteScrollCustomEvent,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonInfiniteScroll,
  IonInfiniteScrollContent,
  IonMenuButton,
  IonModal,
  IonRefresher,
  IonRefresherContent,
  IonSkeletonText,
  IonTitle,
  IonToolbar,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { bagHandleOutline, calendarOutline, chevronForward } from 'ionicons/icons';
import { Reservation, Wishlist, WishlistItem } from '../../core/api/models';
import { ReservationsService } from '../../core/api/reservations.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { eventCountdown, formatDay, formatEventDate } from '../../shared/format';
import { GiftSheet } from '../wishlists/gift-sheet';
import { UserMenu } from '../../shared/user-menu';

/** Lo que tengo reservado. Nadie más ve esta pantalla, y menos los dueños de las listas. */
@Component({
  selector: 'app-reservations',
  imports: [
    UserMenu,
    Avatar,
    RouterLink,
    GiftSheet,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    IonMenuButton,
    IonModal,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './reservations.page.html',
})
export class ReservationsPage implements ViewWillEnter {
  private readonly api = inject(ReservationsService);

  protected readonly reservations = signal<Reservation[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  private readonly page = signal(0);
  private readonly lastPage = signal(1);
  protected readonly hasMore = computed(() => this.page() < this.lastPage());

  /** La reserva del modal. Se conserva al cerrarlo, para que no se vacíe mientras se anima. */
  protected readonly opened = signal<Reservation | null>(null);
  protected readonly modalOpen = signal(false);
  protected readonly sheet =
    typeof matchMedia === 'function' && matchMedia('(max-width: 47.99rem)').matches;

  protected readonly skeletonRows = ['45%', '60%', '35%'];
  protected readonly formatDay = formatDay;
  protected readonly formatEventDate = formatEventDate;
  protected readonly eventCountdown = eventCountdown;

  /**
   * Las reservas juntas por lista: así se ve de un vistazo qué le llevas a cada persona. Las
   * listas quedan en el orden en que aparece su primera reserva, que es la que vence antes.
   */
  protected readonly groups = computed(() => {
    const groups = new Map<number, { wishlist?: Wishlist; reservations: Reservation[] }>();
    for (const reservation of this.reservations()) {
      // Sin lista (no debería pasar), cada reserva va sola.
      const key = reservation.wishlist?.id ?? -reservation.id;
      const group = groups.get(key) ?? { wishlist: reservation.wishlist, reservations: [] };
      group.reservations.push(reservation);
      groups.set(key, group);
    }
    return [...groups.values()];
  });

  constructor() {
    addIcons({ bagHandleOutline, calendarOutline, chevronForward });
  }

  ionViewWillEnter(): void {
    void this.loadPage(1);
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.loadPage(1);
    await event.target.complete();
  }

  protected async loadMore(event: InfiniteScrollCustomEvent): Promise<void> {
    await this.loadPage(this.page() + 1);
    await event.target.complete();
  }

  protected open(reservation: Reservation): void {
    this.opened.set(reservation);
    this.modalOpen.set(true);
  }

  /** Soltada desde la hoja: deja de ser mía y sale de la lista. */
  protected onChanged(item: WishlistItem): void {
    const opened = this.opened();
    if (!opened) {
      return;
    }
    if (!item.reserved_by_me) {
      this.modalOpen.set(false);
      this.reservations.update((list) => list.filter((current) => current.id !== opened.id));
      return;
    }
    this.opened.set({ ...opened, item });
  }

  protected isPast(iso: string): boolean {
    return new Date(iso).getTime() < Date.now();
  }

  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  private async loadPage(page: number): Promise<void> {
    this.error.set(null);
    try {
      const result = await this.api.mine(page);
      this.reservations.update((list) => (page === 1 ? result.data : [...list, ...result.data]));
      this.page.set(result.meta.current_page);
      this.lastPage.set(result.meta.last_page);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar tus reservas.'));
    } finally {
      this.loading.set(false);
    }
  }
}
