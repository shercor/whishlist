import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { IonIcon, IonItem, IonLabel, IonList, IonPopover, NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { filter, map } from 'rxjs';
import { logOutOutline, moonOutline, personCircleOutline, sunnyOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth/auth.service';
import { ThemeService } from '../core/theme/theme.service';
import { Avatar } from './avatar';
import { injectSignOut } from './sign-out';

/**
 * Tu foto en la esquina superior derecha de cada pantalla, y al tocarla el menú de tu cuenta:
 * Mi perfil, modo claro u oscuro y cerrar sesión. Cerrar sesión va al final y detrás de un toque
 * más: suelto en el menú lateral se apretaba sin querer.
 *
 * Va dentro del ion-toolbar de cada página: <ion-buttons slot="end"><app-user-menu /></ion-buttons>.
 * Sin sesión no muestra nada.
 */
@Component({
  selector: 'app-user-menu',
  imports: [Avatar, IonIcon, IonItem, IonLabel, IonList, IonPopover],
  template: `
    @if (auth.user(); as user) {
      <button
        type="button"
        class="user-menu-trigger pressable"
        aria-label="Tu cuenta"
        aria-haspopup="menu"
        [attr.aria-expanded]="open()"
        (click)="show($event)"
      >
        <app-avatar [user]="user" class="size-8" />
      </button>

      <ion-popover
        class="user-menu"
        side="bottom"
        alignment="end"
        [isOpen]="open()"
        [event]="event()"
        (didPresent)="settle($event)"
        (didDismiss)="open.set(false)"
      >
        <ng-template>
          <div class="flex items-center gap-3 px-4 pt-4 pb-3">
            <span class="user-menu-avatar"><app-avatar [user]="user" class="size-10" /></span>
            <div class="min-w-0">
              <!-- Tu nombre aunque no se lo muestres a nadie: este menú solo lo ves tú. -->
              <p class="m-0 truncate font-medium">{{ user.name || user.display_name }}</p>
              <p class="m-0 truncate text-sm text-neutral-500 dark:text-neutral-400">
                {{ user.handle }}
              </p>
            </div>
          </div>
          <ion-list lines="none" role="menu">
            <ion-item
              button
              role="menuitem"
              [detail]="false"
              [class.selected]="onProfile()"
              [attr.aria-current]="onProfile() ? 'page' : null"
              (click)="goToProfile()"
            >
              <ion-icon slot="start" name="person-circle-outline" aria-hidden="true" />
              <ion-label>Mi perfil</ion-label>
            </ion-item>
            <!-- No cierra el menú: así se ve el cambio y se puede volver atrás. -->
            <ion-item button role="menuitem" [detail]="false" (click)="theme.toggle()">
              <ion-icon
                slot="start"
                [name]="theme.isDark() ? 'sunny-outline' : 'moon-outline'"
                aria-hidden="true"
              />
              <ion-label>{{ theme.isDark() ? 'Modo claro' : 'Modo oscuro' }}</ion-label>
            </ion-item>
            <ion-item
              button
              role="menuitem"
              class="user-menu-sign-out"
              [detail]="false"
              (click)="signOutNow()"
            >
              <ion-icon slot="start" name="log-out-outline" aria-hidden="true" />
              <ion-label>Cerrar sesión</ion-label>
            </ion-item>
          </ion-list>
        </ng-template>
      </ion-popover>
    }
  `,
})
export class UserMenu {
  protected readonly auth = inject(AuthService);
  protected readonly theme = inject(ThemeService);
  private readonly nav = inject(NavController);
  private readonly router = inject(Router);
  private readonly signOut = injectSignOut();

  protected readonly open = signal(false);
  /** Si ya estás en Mi perfil: su fila se marca como la sección actual del menú lateral. */
  protected readonly onProfile = toSignal(
    this.router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => isProfile(this.router.url)),
    ),
    { initialValue: isProfile(this.router.url) },
  );
  /** El toque que abrió el menú: el popover se ubica junto a él. */
  protected readonly event = signal<Event | undefined>(undefined);

  constructor() {
    addIcons({ logOutOutline, moonOutline, personCircleOutline, sunnyOutline });
  }

  protected show(event: Event): void {
    this.event.set(event);
    this.open.set(true);
  }

  /**
   * Ionic deja «llena» su animación de entrada: al terminar, la opacidad del envoltorio sigue
   * animada (en 1). Mientras esa animación exista, Chrome corta lo que alcanza a ver el
   * desenfoque, y el vidrio del menú quedaba transparente pero nítido: se leía la pantalla de
   * atrás a través del texto. Al abrirse, se fija el estado final y se suelta la animación; la de
   * salida Ionic la arma de nuevo al cerrar.
   */
  protected settle(event: Event): void {
    const root = (event.target as HTMLElement).shadowRoot;
    root?.querySelectorAll('*').forEach((element) =>
      element.getAnimations?.().forEach((animation) => {
        animation.commitStyles();
        animation.cancel();
      }),
    );
  }

  protected async goToProfile(): Promise<void> {
    this.open.set(false);
    // Como las secciones del menú lateral: Mi perfil es una raíz, no un paso más adelante.
    await this.nav.navigateRoot('/perfil');
  }

  protected async signOutNow(): Promise<void> {
    this.open.set(false);
    await this.signOut();
  }
}

function isProfile(url: string): boolean {
  return url.split(/[?#]/)[0] === '/perfil';
}
