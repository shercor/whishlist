import { Component, computed, inject, input, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonRefresher,
  IonRefresherContent,
  IonSkeletonText,
  IonSpinner,
  IonTitle,
  IonToolbar,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline, lockClosedOutline } from 'ionicons/icons';
import { UserProfile } from '../../core/api/models';
import { PeopleService } from '../../core/api/people.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { formatEventDate, formatShortEventDate, giftCount } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { UserMenu } from '../../shared/user-menu';

/** El perfil de alguien: seguirlo y las listas suyas que puedo abrir. */
@Component({
  selector: 'app-profile',
  imports: [
    UserMenu,
    RouterLink,
    Avatar,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './profile.page.html',
})
export class ProfilePage implements ViewWillEnter {
  private readonly api = inject(PeopleService);
  private readonly auth = inject(AuthService);
  private readonly notify = inject(Notify);

  /** El :username de la ruta. */
  readonly username = input.required<string>();

  protected readonly profile = signal<UserProfile | null>(null);
  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly isMe = computed(() => this.auth.user()?.username === this.username());

  protected readonly skeletonRows = ['45%', '60%'];
  protected readonly formatEventDate = formatEventDate;
  protected readonly formatShortEventDate = formatShortEventDate;
  protected readonly giftCount = giftCount;

  constructor() {
    addIcons({ giftOutline, lockClosedOutline });
  }

  ionViewWillEnter(): void {
    void this.load();
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  protected async follow(): Promise<void> {
    await this.run(async () => {
      await this.api.follow(this.username());
      await this.load();
      await this.notify.toast(
        this.profile()?.follow_status === 'pendiente'
          ? 'Solicitud enviada. Te avisamos cuando responda.'
          : `Ahora sigues a ${this.profile()?.handle}.`,
      );
    });
  }

  protected async cancelRequest(): Promise<void> {
    await this.run(async () => {
      await this.api.unfollow(this.username());
      await this.load();
    });
  }

  protected async unfollow(): Promise<void> {
    const confirmed = await this.notify.confirm(
      `¿Dejar de seguir a ${this.profile()?.handle}?`,
      'Perderás el acceso a sus listas privadas.',
      'Dejar de seguir',
    );
    if (!confirmed) {
      return;
    }
    await this.run(async () => {
      await this.api.unfollow(this.username());
      await this.load();
    });
  }

  private async run(action: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    try {
      await action();
    } catch (error) {
      await this.notify.toast(errorMessage(error, 'No se pudo. Intenta de nuevo.'));
    } finally {
      this.busy.set(false);
    }
  }

  private async load(): Promise<void> {
    this.error.set(null);
    try {
      this.profile.set(await this.api.show(this.username()));
    } catch (error) {
      this.error.set(
        error instanceof HttpErrorResponse && error.status === 404
          ? 'Nadie usa ese usuario.'
          : errorMessage(error, 'No se pudo cargar el perfil.'),
      );
    } finally {
      this.loading.set(false);
    }
  }
}
