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
  IonRefresher,
  IonRefresherContent,
  IonSkeletonText,
  IonTitle,
  IonToolbar,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { calendarOutline, compassOutline, searchOutline } from 'ionicons/icons';
import { Wishlist } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { eventCountdown, formatEventDate, giftCount } from '../../shared/format';
import { UserMenu } from '../../shared/user-menu';

/** Listas públicas de otra gente, para encontrar a quién regalarle. */
@Component({
  selector: 'app-discover',
  imports: [
    UserMenu,
    RouterLink,
    Avatar,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    IonMenuButton,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './discover.page.html',
})
export class DiscoverPage implements ViewWillEnter {
  private readonly api = inject(WishlistsService);

  protected readonly wishlists = signal<Wishlist[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  private readonly page = signal(0);
  private readonly lastPage = signal(1);
  protected readonly hasMore = computed(() => this.page() < this.lastPage());

  protected readonly skeletonRows = ['45%', '60%', '35%', '52%'];
  protected readonly formatEventDate = formatEventDate;
  protected readonly eventCountdown = eventCountdown;
  protected readonly giftCount = giftCount;

  /** Lo escrito en el buscador. */
  protected readonly query = signal('');
  private searchTimer?: ReturnType<typeof setTimeout>;
  /** Cada pedido lleva un número: si llega uno viejo (se siguió escribiendo), se descarta. */
  private request = 0;

  constructor() {
    addIcons({ calendarOutline, compassOutline, searchOutline });
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

  /** Busca al dejar de escribir, no con cada letra. */
  protected typed(event: Event): void {
    this.query.set((event.target as HTMLInputElement).value);
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => void this.loadPage(1), 300);
  }

  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  private async loadPage(page: number): Promise<void> {
    this.error.set(null);
    const request = ++this.request;
    try {
      const result = await this.api.discover(page, this.query());
      if (request !== this.request) {
        return;
      }
      this.wishlists.update((list) => (page === 1 ? result.data : [...list, ...result.data]));
      this.page.set(result.meta.current_page);
      this.lastPage.set(result.meta.last_page);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar las listas.'));
    } finally {
      this.loading.set(false);
    }
  }
}
