import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
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
  addOutline,
  checkmarkCircleOutline,
  chevronForward,
  copyOutline,
  createOutline,
  giftOutline,
  globeOutline,
  lockClosedOutline,
  peopleOutline,
  starOutline,
} from 'ionicons/icons';
import { LockedWishlist, Wishlist, WishlistItem } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { environment } from '../../../environments/environment';
import { formatEventDate } from '../../shared/format';
import { GIFT_STATES, giftStateOf } from '../../shared/gift-state';
import { Notify } from '../../shared/notify';
import { priorityOf } from '../../shared/priority';
import { visibilityOf } from '../../shared/visibility';
import { LockedWishlistView } from '../access/locked-wishlist';
import { GiftSheet } from './gift-sheet';
import { ItemSheet } from './item-sheet';

/**
 * Una lista con sus regalos. Si es mía, para editarla y compartirla (sin una palabra sobre
 * reservas); si no, para que quien regala reserve algo.
 */
@Component({
  selector: 'app-wishlist',
  imports: [
    RouterLink,
    GiftSheet,
    LockedWishlistView,
    ItemSheet,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonModal,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './wishlist.page.html',
})
export class WishlistPage implements ViewWillEnter {
  private readonly api = inject(WishlistsService);
  private readonly notify = inject(Notify);

  /** El :id de la ruta. */
  readonly id = input.required<string>();
  protected readonly numericId = computed(() => Number(this.id()));

  protected readonly wishlist = signal<Wishlist | null>(null);
  protected readonly items = signal<WishlistItem[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  /** Lista privada que no puedo ver: de quién es y si puedo pedirla. */
  protected readonly locked = signal<LockedWishlist | null>(null);

  /** Sin historial, «Volver» lleva a la sección de la que suele venir cada una. */
  protected readonly backHref = computed(() =>
    this.wishlist()?.is_mine === false ? '/descubrir' : '/listas',
  );
  protected readonly ownerName = computed(() => this.wishlist()?.owner?.display_name ?? 'Su dueño');

  protected readonly shareLink = computed(() => {
    const token = this.wishlist()?.share_token;
    return token ? `${environment.shareUrl}${token}` : null;
  });

  /** El regalo del modal. Se conserva al cerrarlo, para que no se vacíe mientras se anima. */
  protected readonly editing = signal<WishlistItem | null>(null);
  protected readonly modalOpen = signal(false);
  /** En el teléfono el modal es una hoja que sube desde abajo. 48rem = md de Tailwind. */
  protected readonly sheet =
    typeof matchMedia === 'function' && matchMedia('(max-width: 47.99rem)').matches;

  protected readonly skeletonRows = ['50%', '35%', '60%'];
  protected readonly formatEventDate = formatEventDate;
  protected readonly priorityOf = priorityOf;
  protected readonly visibilityOf = visibilityOf;
  protected readonly giftStateOf = giftStateOf;
  protected readonly giftStates = GIFT_STATES;

  constructor() {
    addIcons({
      addOutline,
      checkmarkCircleOutline,
      chevronForward,
      copyOutline,
      createOutline,
      giftOutline,
      globeOutline,
      lockClosedOutline,
      peopleOutline,
      starOutline,
    });
  }

  /** Al volver de agregar un regalo o editar la lista, se ve el cambio. */
  ionViewWillEnter(): void {
    void this.load();
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  protected open(item: WishlistItem): void {
    this.editing.set(item);
    this.modalOpen.set(true);
  }

  protected onChanged(item: WishlistItem): void {
    this.items.update((list) => list.map((current) => (current.id === item.id ? item : current)));
    this.editing.set(item);
  }

  protected onStale(): void {
    void this.load();
  }

  protected onRemoved(id: number): void {
    this.modalOpen.set(false);
    this.items.update((list) => list.filter((item) => item.id !== id));
  }

  protected async copyLink(): Promise<void> {
    const link = this.shareLink();
    if (!link) {
      return;
    }

    try {
      await navigator.clipboard.writeText(link);
      await this.notify.toast('Enlace copiado.');
    } catch {
      await this.notify.toast('No se pudo copiar. Selecciona el enlace y cópialo a mano.');
    }
  }

  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  protected async load(): Promise<void> {
    this.error.set(null);
    this.locked.set(null);
    try {
      const wishlist = await this.api.show(Number(this.id()));
      const items = wishlist.items ?? [];
      this.wishlist.set(wishlist);
      this.items.set(items);
      // Si el modal está abierto, que muestre el regalo al día.
      this.editing.update((current) => items.find((item) => item.id === current?.id) ?? current);
    } catch (error) {
      if (error instanceof HttpErrorResponse && error.status === 403 && error.error?.owner) {
        this.locked.set(error.error as LockedWishlist);
      } else {
        this.error.set(loadError(error));
      }
    } finally {
      this.loading.set(false);
    }
  }
}

function loadError(error: unknown): string {
  if (error instanceof HttpErrorResponse && error.status === 404) {
    return 'Esta lista no existe o ya la borraron.';
  }
  return errorMessage(error, 'No se pudo cargar la lista.');
}
