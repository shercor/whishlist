import { Component, computed, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonRefresher,
  IonRefresherContent,
  IonSkeletonText,
  IonTitle,
  IonToolbar,
  RefresherCustomEvent,
  ViewWillEnter,
} from '@ionic/angular';
import { User, Wishlist, WishlistAccess } from '../../core/api/models';
import { AccessService } from '../../core/api/access.service';
import { FollowsService } from '../../core/api/follows.service';
import { WishlistsService } from '../../core/api/wishlists.service';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { ACCESS_SOURCES, ACCESS_STATUSES } from '../../shared/access-status';
import { Notify } from '../../shared/notify';
import { PersonRow } from '../../shared/person-row';
import { UserMenu } from '../../shared/user-menu';

/** «Quién la ve»: quién entró a una lista mía, por dónde, y a qué seguidor más dársela. */
@Component({
  selector: 'app-wishlist-access',
  imports: [
    UserMenu,
    RouterLink,
    PersonRow,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './wishlist-access.page.html',
})
export class WishlistAccessPage implements ViewWillEnter {
  private readonly wishlists = inject(WishlistsService);
  private readonly access = inject(AccessService);
  private readonly follows = inject(FollowsService);
  private readonly auth = inject(AuthService);
  private readonly notify = inject(Notify);

  /** El :id de la ruta. */
  readonly id = input.required<string>();

  protected readonly wishlist = signal<Wishlist | null>(null);
  protected readonly accesses = signal<WishlistAccess[]>([]);
  private readonly followers = signal<User[]>([]);
  /** Seguidores que todavía no tienen nada sobre esta lista. */
  protected readonly invitable = computed(() => {
    const taken = new Set(this.accesses().map((access) => access.user?.username));
    return this.followers().filter((follower) => !taken.has(follower.username));
  });

  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly statuses = ACCESS_STATUSES;
  protected readonly sources = ACCESS_SOURCES;

  ionViewWillEnter(): void {
    void this.load();
  }

  protected async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  protected approve(access: WishlistAccess): Promise<void> {
    return this.run(() => this.access.answer(access.id, 'aprobado'));
  }

  protected async revoke(access: WishlistAccess): Promise<void> {
    const confirmed = await this.notify.confirm(
      `¿Quitarle el acceso a ${access.user?.handle}?`,
      'Deja de ver la lista en el acto. Después puedes volver a dársela.',
      'Quitar',
    );
    if (confirmed) {
      await this.run(() => this.access.revoke(Number(this.id()), access.id));
    }
  }

  protected async invite(follower: User): Promise<void> {
    await this.run(async () => {
      await this.access.invite(Number(this.id()), follower.username);
      await this.notify.toast(`Le diste la lista a ${follower.handle}. Le llega un aviso.`);
    });
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
    const id = Number(this.id());
    try {
      const [wishlist, access, follows] = await Promise.all([
        this.wishlists.show(id),
        this.access.lists(),
        this.follows.lists(),
      ]);
      this.wishlist.set(wishlist);
      this.accesses.set(access.received.filter((item) => item.wishlist?.id === id));
      this.followers.set(
        follows.followers.flatMap((follow) => (follow.follower ? [follow.follower] : [])),
      );
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo cargar quién ve la lista.'));
    } finally {
      this.loading.set(false);
    }
  }
}
