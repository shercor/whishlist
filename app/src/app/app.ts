import { Component, computed, effect, inject, isDevMode, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink } from '@angular/router';
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
  notificationsOutline,
  peopleOutline,
} from 'ionicons/icons';
import { AuthService } from './core/auth/auth.service';
import { ThemeService } from './core/theme/theme.service';
import { PageTransitionService } from './core/transitions/page-transition.service';
import { AdminMenu } from './features/admin/admin-menu';
import {
  SECTIONS,
  Section,
  injectCurrentPath,
  sectionCount,
  sectionFor,
} from './shared/navigation';
import { TabBar } from './shared/tab-bar';

/**
 * Las secciones: desde md, en el menú fijo a la izquierda; en el teléfono, en la barra inferior
 * (tab-bar.ts), y el menú lateral se apaga. Sin sesión no hay ni uno ni otra, así el login ocupa
 * toda la pantalla.
 */
@Component({
  selector: 'app-root',
  imports: [
    RouterLink,
    AdminMenu,
    TabBar,
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
        <!-- Apagado en el teléfono: ahí está la barra inferior, y sin menú su botón ☰ se esconde solo. -->
        <ion-menu contentId="main" class="app-menu" [disabled]="!auth.isAuthenticated() || !wide()">
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
                  theme.isDark()
                    ? 'logo-wishlist-sobre-oscuro.png'
                    : 'logo-wishlist-sobre-claro.png'
                "
                alt="wishlist"
              />
            </div>
            <ion-list lines="none">
              @for (section of sections; track section.url) {
                <ion-menu-toggle [autoHide]="false">
                  <!-- Marcada a mano y no con routerLinkActive: Notificaciones son dos rutas. -->
                  <ion-item
                    [routerLink]="section.url"
                    routerDirection="root"
                    [detail]="false"
                    [class.selected]="section === activeSection()"
                    [attr.aria-current]="section === activeSection() ? 'page' : null"
                  >
                    <ion-icon slot="start" [name]="section.icon" aria-hidden="true" />
                    <ion-label>{{ section.title }}</ion-label>
                    @if (countOf(section); as count) {
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

      @if (showTabBar()) {
        <app-tab-bar />
      }

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
  /** La animación al cambiar de pantalla. Sin elegir, el fundido. */
  protected readonly transitions = inject(PageTransitionService);
  /** El menú de administrador no llega a producción. */
  protected readonly devMode = isDevMode();
  private readonly router = inject(Router);
  protected readonly sections = SECTIONS;
  private readonly path = injectCurrentPath();
  protected readonly activeSection = computed(() => sectionFor(this.path()));

  /** Desde md (48rem) el menú queda fijo al lado; más angosto, es teléfono. */
  private readonly wideQuery =
    typeof matchMedia === 'function' ? matchMedia('(min-width: 48rem)') : undefined;
  protected readonly wide = signal(this.wideQuery?.matches ?? true);

  /**
   * La barra inferior: con sesión, en todas las pantallas, para cambiar de sección desde cualquier
   * parte sin volver atrás. En las de más adentro ninguna sección queda marcada. En escritorio
   * igual se pide, pero el CSS la esconde: así no depende de que matchMedia avise el cambio de
   * ancho a tiempo.
   */
  protected readonly showTabBar = computed(() => this.auth.isAuthenticated());

  constructor() {
    addIcons({
      bagHandleOutline,
      compassOutline,
      giftOutline,
      notificationsOutline,
      peopleOutline,
    });

    this.wideQuery?.addEventListener('change', (event) => this.wide.set(event.matches));

    // Con la barra inferior a la vista, el contenido de cada pantalla deja espacio abajo para no
    // quedar tapado (tailwind.css, .with-tab-bar).
    effect(() => document.documentElement.classList.toggle('with-tab-bar', this.showTabBar()));

    // Al cambiar de pantalla, los contadores del menú se ponen al día.
    this.router.events
      .pipe(
        filter((event) => event instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => void this.auth.refresh());
  }

  protected countOf(section: Section): number {
    return sectionCount(section, this.auth.counts());
  }
}
