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
  mailUnreadOutline,
  notificationsOutline,
  peopleOutline,
  searchOutline,
} from 'ionicons/icons';
import { AuthService } from './core/auth/auth.service';
import { ThemeService } from './core/theme/theme.service';
import { PageTransitionService } from './core/transitions/page-transition.service';
import { AdminMenu } from './features/admin/admin-menu';
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
            <div class="px-5 pt-6 pb-5">
              <!--
                Logo anterior: el regalo ámbar con el nombre escrito. Guardado por si se vuelve a él;
                para usarlo, descomentarlo y quitar el <img> de abajo.
                <p class="m-0 text-xl font-semibold tracking-tight">
                  <span class="title-gift" aria-hidden="true"></span>whishlist
                </p>
              -->
              <!--
                El logo en imagen (public/). Dos versiones del mismo archivo: el original, con el
                texto casi blanco, para el modo oscuro; y otra con el texto en gris oscuro, porque
                sobre el menú blanco del modo claro el blanco no se ve. La estrella es la misma.
              -->
              <img
                class="app-logo"
                [src]="
                  theme.isDark() ? 'logo-wishlist-sobre-oscuro.png' : 'logo-wishlist-sobre-claro.png'
                "
                alt="wishlist"
              />
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
}
