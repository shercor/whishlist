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
import { Follow, FollowLists, User } from '../../core/api/models';
import { FollowsService } from '../../core/api/follows.service';
import { PeopleService } from '../../core/api/people.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { Notify } from '../../shared/notify';
import { PersonRow } from '../../shared/person-row';
import { UserMenu } from '../../shared/user-menu';

/** Quién me sigue y a quién sigo. Solo un seguidor puede recibir una lista privada mía. */
@Component({
  selector: 'app-follows',
  imports: [
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
  templateUrl: './follows.page.html',
})
export class FollowsPage implements ViewWillEnter {
  private readonly follows = inject(FollowsService);
  private readonly people = inject(PeopleService);
  protected readonly auth = inject(AuthService);
  private readonly notify = inject(Notify);

  protected readonly lists = signal<FollowLists | null>(null);
  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  ionViewWillEnter(): void {
    void this.load();
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  protected answer(follow: Follow, decision: 'aceptar' | 'rechazar'): Promise<void> {
    return this.run(() => this.follows.answer(follow.id, decision));
  }

  protected async remove(follower: User): Promise<void> {
    const confirmed = await this.notify.confirm(
      `¿Quitar a ${follower.handle}?`,
      'Perderá el acceso a tus listas privadas.',
      'Quitar',
    );
    if (confirmed) {
      await this.run(() => this.follows.removeFollower(follower.username));
    }
  }

  protected cancel(followed: User): Promise<void> {
    return this.run(() => this.people.unfollow(followed.username));
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
      this.lists.set(await this.follows.lists());
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo cargar tu gente.'));
    } finally {
      this.loading.set(false);
    }
  }
}
