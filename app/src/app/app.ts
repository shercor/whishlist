import { Component, inject, isDevMode } from '@angular/core';
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
  moonOutline,
  notificationsOutline,
  peopleOutline,
  searchOutline,
  sunnyOutline,
} from 'ionicons/icons';
import { AuthService } from './core/auth/auth.service';
import { ThemeService } from './core/theme/theme.service';
import { PageTransitionService } from './core/transitions/page-transition.service';
import { AdminMenu } from './features/admin/admin-menu';
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
    AdminMenu,
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
              <p class="m-0 text-xl font-semibold tracking-tight">
                <span class="title-gift" aria-hidden="true"></span>whishlist
              </p>
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
                        class="min-w-5 rounded-full bg-[var(--ion-color-primary)] px-1.5 text-center text-xs leading-5 font-semibold text-[var(--ion-color-primary-contrast)]"
                        [attr.aria-label]="count + ' por ver'"
                        >{{ count }}</span
                      >
                    }
                  </ion-item>
                </ion-menu-toggle>
              }
              <!-- Sin ion-menu-toggle: en el teléfono el menú queda abierto y se ve el cambio. -->
              <ion-item button [detail]="false" (click)="theme.toggle()">
                <ion-icon
                  slot="start"
                  [name]="theme.isDark() ? 'sunny-outline' : 'moon-outline'"
                  aria-hidden="true"
                />
                <ion-label>{{ theme.isDark() ? 'Modo claro' : 'Modo oscuro' }}</ion-label>
              </ion-item>
              <ion-menu-toggle [autoHide]="false">
                <ion-item button [detail]="false" (click)="logout()">
                  <ion-icon slot="start" name="log-out-outline" aria-hidden="true" />
                  <ion-label>Cerrar sesión</ion-label>
                </ion-item>
              </ion-menu-toggle>
            </ion-list>
          </ion-content>
        </ion-menu>
        <ion-router-outlet id="main" [animation]="transitions.builder()" />
      </ion-split-pane>

      @if (devMode && auth.isAuthenticated()) {
        <app-admin-menu
          class="fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-[999] flex flex-col items-end"
        />
      }
    </ion-app>
  `,
})
export class App {
  protected readonly auth = inject(AuthService);
  /** Se crea al arrancar: mantiene la clase del tema y sigue al sistema si no hay elección. */
  protected readonly theme = inject(ThemeService);
  /** La animación al cambiar de pantalla. Sin elegir, la de Ionic de siempre. */
  protected readonly transitions = inject(PageTransitionService);
  /** El menú de administrador no llega a producción. */
  protected readonly devMode = isDevMode();
  private readonly router = inject(Router);
  protected readonly sections = SECTIONS;

  constructor() {
    addIcons({
      bagHandleOutline,
      compassOutline,
      giftOutline,
      logOutOutline,
      mailUnreadOutline,
      moonOutline,
      notificationsOutline,
      peopleOutline,
      searchOutline,
      sunnyOutline,
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
