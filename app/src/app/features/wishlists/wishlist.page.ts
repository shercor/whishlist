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
  copyOutline,
  createOutline,
  giftOutline,
  globeOutline,
  lockClosedOutline,
  starOutline,
} from 'ionicons/icons';
import { Wishlist, WishlistItem } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { environment } from '../../../environments/environment';
import { formatEventDate } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { priorityOf } from '../../shared/priority';
import { visibilityOf } from '../../shared/visibility';
import { ItemSheet } from './item-sheet';

/** Una lista con sus regalos. */
@Component({
  selector: 'app-wishlist',
  imports: [
    RouterLink,
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

  protected readonly wishlist = signal<Wishlist | null>(null);
  protected readonly items = signal<WishlistItem[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

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

  constructor() {
    addIcons({
      addOutline,
      checkmarkCircleOutline,
      copyOutline,
      createOutline,
      giftOutline,
      globeOutline,
      lockClosedOutline,
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

  private async load(): Promise<void> {
    this.error.set(null);
    try {
      const wishlist = await this.api.show(Number(this.id()));
      this.wishlist.set(wishlist);
      this.items.set(wishlist.items ?? []);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo cargar la lista.'));
    } finally {
      this.loading.set(false);
    }
  }
}
