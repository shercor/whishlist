import { Component, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { filter } from 'rxjs';
import {
  IonApp,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonMenu,
  IonMenuToggle,
  IonRouterOutlet,
  IonSplitPane,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  bagHandleOutline,
  compassOutline,
  giftOutline,
  logOutOutline,
  mailUnreadOutline,
  notificationsOutline,
  peopleOutline,
  searchOutline,
} from 'ionicons/icons';
import { AuthService } from './core/auth/auth.service';
import { Avatar } from './shared/avatar';
import { SECTIONS } from './shared/navigation';

/**
 * Menú de secciones: fijo a la izquierda desde md, y en el teléfono se abre con el botón de
 * cada barra superior. Sin sesión se desactiva, así el login ocupa toda la pantalla.
 */
@Component({
  selector: 'app-root',
  imports: [
    RouterLink,
    RouterLinkActive,
    Avatar,
    IonApp,
    IonContent,
    IonIcon,
    IonItem,
    IonLabel,
    IonList,
    IonMenu,
    IonMenuToggle,
    IonRouterOutlet,
    IonSplitPane,
  ],
  template: `
    <ion-app>
      <ion-split-pane contentId="main" when="md">
        <ion-menu contentId="main" class="app-menu" [disabled]="!auth.isAuthenticated()">
          <ion-content>
            <div class="px-4 pt-6 pb-4">
              <p class="m-0 text-xl font-semibold tracking-tight">whishlist</p>
              @if (auth.user(); as user) {
                <ion-menu-toggle [autoHide]="false">
                  <a
                    routerLink="/perfil"
                    routerDirection="root"
                    class="tappable-row -mx-2 mt-3 flex items-center gap-3 rounded-lg px-2 py-1.5 text-inherit no-underline"
                    aria-label="Mi perfil"
                  >
                    <app-avatar [user]="user" class="size-10" />
                    <div class="min-w-0">
                      <!-- Tu nombre aunque no se lo muestres a nadie: el menú solo lo ves tú. -->
                      <p class="m-0 truncate font-medium">{{ user.name || user.display_name }}</p>
                      <p class="m-0 truncate text-sm text-neutral-500 dark:text-neutral-400">
                        {{ user.handle }} · Mi perfil
                      </p>
                    </div>
                  </a>
                </ion-menu-toggle>
              }
            </div>
            <ion-list lines="none">
              @for (section of sections; track section.url) {
                <ion-menu-toggle [autoHide]="false">
                  <ion-item
                    [routerLink]="section.url"
                    routerLinkActive="selected"
                    routerDirection="root"
                    [detail]="false"
                  >
                    <ion-icon slot="start" [name]="section.icon" aria-hidden="true" />
                    <ion-label>{{ section.title }}</ion-label>
                    @if (section.count && auth.counts()[section.count]; as count) {
                      <span
                        slot="end"
                        class="min-w-5 rounded-full bg-[var(--ion-color-primary)] px-1.5 text-center text-xs leading-5 font-semibold text-white"
                        [attr.aria-label]="count + ' por ver'"
                        >{{ count }}</span
                      >
                    }
                  </ion-item>
                </ion-menu-toggle>
              }
              <ion-menu-toggle [autoHide]="false">
                <ion-item button [detail]="false" (click)="logout()">
                  <ion-icon slot="start" name="log-out-outline" aria-hidden="true" />
                  <ion-label>Cerrar sesión</ion-label>
                </ion-item>
              </ion-menu-toggle>
            </ion-list>
          </ion-content>
        </ion-menu>
        <ion-router-outlet id="main" />
      </ion-split-pane>
    </ion-app>
  `,
})
export class App {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  protected readonly sections = SECTIONS;

  constructor() {
    addIcons({
      bagHandleOutline,
      compassOutline,
      giftOutline,
      logOutOutline,
      mailUnreadOutline,
      notificationsOutline,
      peopleOutline,
      searchOutline,
    });

    // Al cambiar de pantalla, los contadores del menú se ponen al día.
    this.router.events
      .pipe(
        filter((event) => event instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => void this.auth.refresh());
  }

  protected async logout(): Promise<void> {
    try {
      await this.auth.logout();
    } catch {
      // La sesión local ya se borró: el token que quedó en la API vence solo.
    }
    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }
}
