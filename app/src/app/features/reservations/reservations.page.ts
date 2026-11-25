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
import {
  alertCircleOutline,
  bagHandleOutline,
  calendarOutline,
  chevronForward,
  star,
  timeOutline,
} from 'ionicons/icons';
import { Reservation, Wishlist, WishlistItem } from '../../core/api/models';
import { ReservationsService } from '../../core/api/reservations.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import {
  eventCountdown,
  formatDay,
  formatEventDate,
  formatPrice,
  releaseCountdown,
} from '../../shared/format';
import { priorityOf } from '../../shared/priority';
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
  /** Cuántas reservas tengo en total, también las de páginas que aún no se cargan. */
  protected readonly total = signal(0);
  protected readonly error = signal<string | null>(null);

  private readonly page = signal(0);
  private readonly lastPage = signal(1);
  protected readonly hasMore = computed(() => this.page() < this.lastPage());

  /** La reserva del modal. Se conserva al cerrarlo, para que no se vacíe mientras se anima. */
  protected readonly opened = signal<Reservation | null>(null);
  protected readonly modalOpen = signal(false);
  protected readonly sheet =
    typeof matchMedia === 'function' && matchMedia('(max-width: 47.99rem)').matches;

  protected readonly skeletonRows = ['70%', '55%'];
  protected readonly formatDay = formatDay;
  protected readonly formatEventDate = formatEventDate;
  protected readonly eventCountdown = eventCountdown;
  protected readonly releaseCountdown = releaseCountdown;
  protected readonly formatPrice = formatPrice;
  protected readonly priorityOf = priorityOf;

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

  /** A cuántas personas les regalo, contando las reservas ya cargadas. */
  protected readonly people = computed(
    () => new Set(this.reservations().map((r) => r.wishlist?.owner?.username ?? `#${r.id}`)).size,
  );

  /**
   * La suma de los precios de referencia, si se puede dar entera: con todas las reservas cargadas
   * y una sola moneda. Si no, null y el resumen no la muestra.
   */
  protected readonly spend = computed(() => {
    if (this.hasMore()) {
      return null;
    }
    const products = this.reservations()
      .map((r) => r.item.product)
      .filter((product) => product?.reference_price);
    const currencies = new Set(products.map((product) => product?.currency ?? 'CLP'));
    if (products.length === 0 || currencies.size > 1) {
      return null;
    }
    const sum = products.reduce((acc, product) => acc + Number(product?.reference_price), 0);
    return formatPrice(sum, [...currencies][0]);
  });

  constructor() {
    addIcons({
      alertCircleOutline,
      bagHandleOutline,
      calendarOutline,
      chevronForward,
      star,
      timeOutline,
    });
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
      this.total.update((count) => Math.max(0, count - 1));
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
      this.total.set(result.meta.total);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar tus reservas.'));
    } finally {
      this.loading.set(false);
    }
  }
}
