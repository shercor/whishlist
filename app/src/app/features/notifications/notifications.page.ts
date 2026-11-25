import { Component, computed, inject, signal } from '@angular/core';
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
  NavController,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { notificationsOutline } from 'ionicons/icons';
import { AppNotification } from '../../core/api/models';
import { NotificationsService } from '../../core/api/notifications.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { appPathFor } from '../../shared/app-path';
import { formatAgo } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { UserMenu } from '../../shared/user-menu';

/** Lo que pasó mientras no estabas. Tocar un aviso lo marca leído y lleva a lo que cuenta. */
@Component({
  selector: 'app-notifications',
  imports: [
    UserMenu,
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
  templateUrl: './notifications.page.html',
})
export class NotificationsPage implements ViewWillEnter {
  private readonly api = inject(NotificationsService);
  private readonly auth = inject(AuthService);
  private readonly nav = inject(NavController);
  private readonly notify = inject(Notify);

  protected readonly notifications = signal<AppNotification[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly hasUnread = computed(() => this.notifications().some((n) => !n.read));

  private readonly page = signal(0);
  private readonly total = signal(0);
  protected readonly hasMore = computed(() => this.notifications().length < this.total());

  protected readonly skeletonRows = ['55%', '40%', '65%'];
  protected readonly formatAgo = formatAgo;

  constructor() {
    addIcons({ notificationsOutline });
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

  protected async open(notification: AppNotification): Promise<void> {
    if (!notification.read) {
      this.setRead((n) => n.id === notification.id);
      void this.api
        .markRead(notification.id)
        .then(() => this.auth.refresh())
        .catch(() => undefined);
    }

    const path = appPathFor(notification.url);
    if (path) {
      await this.nav.navigateForward(path);
    }
  }

  protected async readAll(): Promise<void> {
    try {
      await this.api.readAll();
      this.setRead(() => true);
      await this.auth.refresh();
    } catch (error) {
      await this.notify.toast(errorMessage(error, 'No se pudieron marcar. Intenta de nuevo.'));
    }
  }

  protected staggerIndex(index: number): number {
    return Math.min(index, 10);
  }

  private setRead(which: (n: AppNotification) => boolean): void {
    this.notifications.update((list) => list.map((n) => (which(n) ? { ...n, read: true } : n)));
  }

  private async loadPage(page: number): Promise<void> {
    this.error.set(null);
    try {
      const result = await this.api.list(page);
      this.notifications.update((list) => (page === 1 ? result.data : [...list, ...result.data]));
      this.page.set(result.meta.current_page);
      this.total.set(result.meta.total);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar los avisos.'));
    } finally {
      this.loading.set(false);
    }
  }
}
