import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, computed, inject, input, signal, viewChild } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
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
  ViewWillLeave,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  addOutline,
  checkmarkCircleOutline,
  createOutline,
  eyeOffOutline,
  giftOutline,
  globeOutline,
  lockClosedOutline,
  chevronBack,
  chevronForward,
  shareSocialOutline,
  star,
} from 'ionicons/icons';
import { LockedWishlist, Page, Wishlist, WishlistItem } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { environment } from '../../../environments/environment';
import { formatEventDate, formatPrice } from '../../shared/format';
import { GIFT_STATES, giftStateOf } from '../../shared/gift-state';
import { Notify } from '../../shared/notify';
import { priorityOf } from '../../shared/priority';
import { visibilityOf } from '../../shared/visibility';
import { LockedWishlistView } from '../access/locked-wishlist';
import { GiftSheet } from './gift-sheet';
import { ItemSheet } from './item-sheet';
import { ShareSheet } from './share-sheet';

/** Regalos por página. */
const PER_PAGE = 10;

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
    ShareSheet,
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
export class WishlistPage implements ViewWillEnter, ViewWillLeave {
  private readonly api = inject(WishlistsService);
  private readonly notify = inject(Notify);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  /** Si esta pantalla es la que se ve: una página que llega tarde no debe tocar la dirección de otra. */
  private active = false;

  /** El :id de la ruta. */
  readonly id = input.required<string>();
  /** ?pagina= de la dirección: así recargar o compartir el enlace deja en la misma página. */
  readonly pagina = input<string>();
  protected readonly numericId = computed(() => Number(this.id()));

  protected readonly wishlist = signal<Wishlist | null>(null);
  /** Los regalos de la página que se ve, no todos. */
  protected readonly items = signal<WishlistItem[]>([]);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  /** Cuántos regalos tiene la lista entera. */
  protected readonly total = signal(0);
  /** Cambiando de página: la que se ve se atenúa hasta que llega la otra. */
  protected readonly pageLoading = signal(false);
  protected readonly pageNumbers = computed(() => pageNumbers(this.page(), this.lastPage()));
  /** «11–20 de 26». */
  protected readonly pageRange = computed(() => {
    const from = (this.page() - 1) * PER_PAGE + 1;
    return `${from}–${from + this.items().length - 1} de ${this.total()}`;
  });
  /** Adonde sube la pantalla al cambiar de página: el título de los regalos. */
  private readonly giftsTop = viewChild<ElementRef<HTMLElement>>('giftsTop');
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
    // En producción shareUrl es relativa, y lo que se copia tiene que ser la dirección completa.
    return token ? new URL(`${environment.shareUrl}${token}`, location.origin).href : null;
  });

  /** El regalo del modal. Se conserva al cerrarlo, para que no se vacíe mientras se anima. */
  protected readonly editing = signal<WishlistItem | null>(null);
  protected readonly modalOpen = signal(false);
  /** La hoja con el enlace para compartir y «Quién la ve». */
  protected readonly shareOpen = signal(false);
  /** En el teléfono el modal es una hoja que sube desde abajo. 48rem = md de Tailwind. */
  protected readonly sheet =
    typeof matchMedia === 'function' && matchMedia('(max-width: 47.99rem)').matches;

  protected readonly skeletonRows = ['70%', '55%', '80%'];
  protected readonly formatEventDate = formatEventDate;
  protected readonly formatPrice = formatPrice;
  protected readonly priorityOf = priorityOf;
  protected readonly visibilityOf = visibilityOf;
  protected readonly giftStateOf = giftStateOf;
  protected readonly giftStates = GIFT_STATES;

  constructor() {
    addIcons({
      addOutline,
      checkmarkCircleOutline,
      createOutline,
      eyeOffOutline,
      giftOutline,
      globeOutline,
      lockClosedOutline,
      chevronBack,
      chevronForward,
      shareSocialOutline,
      star,
    });
  }

  /** Al volver de agregar un regalo o editar la lista, se ve el cambio. */
  ionViewWillEnter(): void {
    this.active = true;
    this.page.set(pageFromQuery(this.pagina()));
    void this.load();
  }

  ionViewWillLeave(): void {
    this.active = false;
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
    this.total.update((total) => total - 1);
    // Lo que venía en la página siguiente sube a llenar el hueco.
    void this.fetchPage(this.page(), false);
  }

  protected async goTo(page: number): Promise<void> {
    if (page === this.page() || page < 1 || page > this.lastPage() || this.pageLoading()) {
      return;
    }
    await this.fetchPage(page, true);
  }

  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  /** La lista y la página de regalos en que se estaba, a la vez. */
  protected async load(): Promise<void> {
    this.error.set(null);
    this.locked.set(null);
    const id = this.numericId();
    const [wishlist, page] = await Promise.allSettled([
      this.api.show(id),
      this.pageOf(id, this.page()),
    ]);
    try {
      // La lista primero: si no se puede ver, su 403 dice de quién es y si se puede pedir; el de
      // los regalos es seco.
      this.wishlist.set(settled(wishlist));
      this.showPage(settled(page));
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

  private async fetchPage(page: number, scroll: boolean): Promise<void> {
    this.pageLoading.set(true);
    try {
      this.showPage(await this.pageOf(this.numericId(), page));
      if (scroll) {
        this.giftsTop()?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } catch (error) {
      await this.notify.toast(errorMessage(error, 'No se pudo cargar esa página.'));
    } finally {
      this.pageLoading.set(false);
    }
  }

  /** Una página de regalos; si ya no existe (se quitaron regalos), la última que queda. */
  private async pageOf(id: number, page: number): Promise<Page<WishlistItem>> {
    const result = await this.api.items(id, page, PER_PAGE);
    return result.data.length === 0 && result.meta.last_page < page
      ? this.api.items(id, result.meta.last_page, PER_PAGE)
      : result;
  }

  private showPage(result: Page<WishlistItem>): void {
    const items = result.data;
    this.items.set(items);
    this.page.set(result.meta.current_page);
    this.lastPage.set(result.meta.last_page);
    this.total.set(result.meta.total);
    // Si el modal está abierto, que muestre el regalo al día.
    this.editing.update((current) => items.find((item) => item.id === current?.id) ?? current);
    this.syncUrl(result.meta.current_page);
  }

  /**
   * Deja la página en la dirección (?pagina=3; la primera, sin nada). Reemplaza la entrada del
   * historial en vez de sumar una: «Volver» lleva a la pantalla anterior, no a la página anterior.
   */
  private syncUrl(page: number): void {
    const wanted = page > 1 ? String(page) : undefined;
    if (!this.active || this.pagina() === wanted) {
      return;
    }
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { pagina: wanted ?? null },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }
}

/** El número de ?pagina=, o la primera si no viene o no es un número válido. */
function pageFromQuery(value: string | undefined): number {
  const page = Number(value);
  return Number.isInteger(page) && page >= 1 ? page : 1;
}

function settled<T>(result: PromiseSettledResult<T>): T {
  if (result.status === 'rejected') {
    throw result.reason;
  }
  return result.value;
}

/**
 * Los números de página a mostrar. Todos si son pocos; si no, la primera, la última y las vecinas
 * de la actual, con null donde va «…»: 1 … 4 5 6 … 12.
 */
export function pageNumbers(current: number, last: number): (number | null)[] {
  if (last <= 7) {
    return Array.from({ length: last }, (_, index) => index + 1);
  }
  const shown = [...new Set([1, current - 1, current, current + 1, last])]
    .filter((page) => page >= 1 && page <= last)
    .sort((a, b) => a - b);
  return shown.flatMap((page, index) =>
    index > 0 && page - shown[index - 1] > 1 ? [null, page] : [page],
  );
}

function loadError(error: unknown): string {
  if (error instanceof HttpErrorResponse && error.status === 404) {
    return 'Esta lista no existe o ya la borraron.';
  }
  return errorMessage(error, 'No se pudo cargar la lista.');
}
