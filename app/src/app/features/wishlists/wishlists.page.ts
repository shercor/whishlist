import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import {
  InfiniteScrollCustomEvent,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonInfiniteScroll,
  IonInfiniteScrollContent,
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
  giftOutline,
  globeOutline,
  lockClosedOutline,
  logOutOutline,
} from 'ionicons/icons';
import { Wishlist } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { formatEventDate, formatShortEventDate, giftCount } from '../../shared/format';
import { visibilityOf } from '../../shared/visibility';

@Component({
  selector: 'app-wishlists',
  imports: [
    RouterLink,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './wishlists.page.html',
})
export class WishlistsPage implements ViewWillEnter {
  private readonly api = inject(WishlistsService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly wishlists = signal<Wishlist[]>([]);
  /** Primera carga: la vista aún no tiene nada que mostrar. */
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  private readonly page = signal(0);
  private readonly lastPage = signal(1);
  protected readonly hasMore = computed(() => this.page() < this.lastPage());

  /** Ancho del nombre en cada fila del esqueleto: distintos, para que parezca una lista real. */
  protected readonly skeletonRows = ['45%', '60%', '35%', '52%'];

  protected readonly formatEventDate = formatEventDate;
  protected readonly formatShortEventDate = formatShortEventDate;
  protected readonly giftCount = giftCount;
  protected readonly visibilityOf = visibilityOf;

  constructor() {
    addIcons({ addOutline, giftOutline, globeOutline, lockClosedOutline, logOutOutline });
  }

  /** Al volver de crear una lista, la nueva ya aparece. */
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

  protected async logout(): Promise<void> {
    try {
      await this.auth.logout();
    } catch {
      // La sesión local ya se borró: el token que quedó en la API vence solo.
    }
    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }

  /** Limita el escalonado de la entrada, para que una lista larga no tarde en aparecer. */
  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  private async loadPage(page: number): Promise<void> {
    this.error.set(null);

    try {
      const result = await this.api.mine(page);
      this.wishlists.update((list) => (page === 1 ? result.data : [...list, ...result.data]));
      this.page.set(result.meta.current_page);
      this.lastPage.set(result.meta.last_page);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar tus listas.'));
    } finally {
      this.loading.set(false);
    }
  }
}
