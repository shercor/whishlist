import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonMenuButton,
  IonRefresher,
  IonRefresherContent,
  IonSkeletonText,
  IonTitle,
  IonToolbar,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { AccessStatus, Follow, WishlistAccess } from '../../core/api/models';
import { AccessService } from '../../core/api/access.service';
import { FollowsService } from '../../core/api/follows.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { ACCESS_STATUSES } from '../../shared/access-status';
import { formatDay } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { PersonRow } from '../../shared/person-row';
import { UserMenu } from '../../shared/user-menu';
import { NotificationsSwitch } from '../notifications/notifications-switch';

/** Todo lo que espera mi respuesta, y qué pasó con lo que pedí yo. */
@Component({
  selector: 'app-requests',
  imports: [
    NotificationsSwitch,
    UserMenu,
    RouterLink,
    PersonRow,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonMenuButton,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './requests.page.html',
})
export class RequestsPage implements ViewWillEnter {
  private readonly access = inject(AccessService);
  private readonly follows = inject(FollowsService);
  private readonly auth = inject(AuthService);
  private readonly notify = inject(Notify);

  protected readonly followRequests = signal<Follow[]>([]);
  protected readonly received = signal<WishlistAccess[]>([]);
  protected readonly sent = signal<WishlistAccess[]>([]);
  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly statuses = ACCESS_STATUSES;
  protected readonly formatDay = formatDay;

  ionViewWillEnter(): void {
    void this.load();
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  protected answerFollow(follow: Follow, decision: 'aceptar' | 'rechazar'): Promise<void> {
    return this.run(() => this.follows.answer(follow.id, decision));
  }

  protected async answerAccess(
    request: WishlistAccess,
    status: Exclude<AccessStatus, 'pendiente'>,
  ): Promise<void> {
    if (status === 'revocado') {
      const confirmed = await this.notify.confirm(
        `¿Quitarle el acceso a ${request.user?.handle}?`,
        'Deja de ver la lista en el acto.',
        'Quitar acceso',
      );
      if (!confirmed) {
        return;
      }
    }
    await this.run(() => this.access.answer(request.id, status));
  }

  private async run(action: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    try {
      await action();
      await Promise.all([this.load(), this.auth.refresh()]);
    } catch (error) {
      await this.notify.toast(errorMessage(error, 'No se pudo. Intenta de nuevo.'));
    } finally {
      this.busy.set(false);
    }
  }

  private async load(): Promise<void> {
    this.error.set(null);
    try {
      const [follows, access] = await Promise.all([this.follows.lists(), this.access.lists()]);
      this.followRequests.set(follows.pending_for_me);
      // Los que invitaste o entraron con el enlace no te pidieron nada: están en «Quién la ve».
      this.received.set(access.received.filter((request) => request.source === 'solicitud'));
      this.sent.set(access.sent);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudieron cargar las solicitudes.'));
    } finally {
      this.loading.set(false);
    }
  }
}
