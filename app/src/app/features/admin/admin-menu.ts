import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonButton, IonIcon, NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, constructOutline, playOutline } from 'ionicons/icons';
import { PageTransitionService } from '../../core/transitions/page-transition.service';
import { SECTIONS } from '../../shared/navigation';

/**
 * Menú de administrador, abajo a la derecha: por ahora, elegir la animación al cambiar de
 * pantalla. Solo existe mientras se desarrolla (app.ts lo muestra con isDevMode()).
 */
@Component({
  selector: 'app-admin-menu',
  imports: [IonButton, IonIcon],
  host: { '(document:keydown.escape)': 'open.set(false)' },
  template: `
    @if (open()) {
      <section
        id="admin-menu"
        aria-labelledby="admin-menu-title"
        animate.enter="enter-up"
        class="card mb-2 flex max-h-[70vh] w-80 max-w-[calc(100vw-2rem)] flex-col overflow-hidden"
      >
        <header class="border-b border-neutral-100 px-4 pt-3 pb-2 dark:border-white/5">
          <h2 id="admin-menu-title" class="section-title mb-1">Administrador</h2>
          <p class="m-0 text-sm font-medium">Animación al cambiar de pantalla</p>
          <p class="hint m-0 mt-0.5">
            Elige una y cambia de sección en el menú, o usa «Probar». Al volver se invierte.
          </p>
        </header>

        <div role="radiogroup" aria-label="Animación" class="flex-1 overflow-y-auto px-2 py-2">
          @for (option of transitions.options; track option.id) {
            <label
              class="tappable-row flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2"
              [class]="
                option.id === transitions.current().id ? 'bg-amber-50 dark:bg-amber-500/15' : ''
              "
            >
              <input
                type="radio"
                name="page-transition"
                class="mt-0.5 size-4 shrink-0 accent-[var(--ion-color-primary)]"
                [checked]="option.id === transitions.current().id"
                (change)="transitions.choose(option.id)"
              />
              <span class="min-w-0">
                <span class="block text-sm font-medium">{{ option.name }}</span>
                <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                  {{ option.description }}
                </span>
              </span>
            </label>
          }
        </div>

        <footer class="border-t border-neutral-100 px-4 py-3 dark:border-white/5">
          <ion-button expand="block" class="pressable m-0" (click)="tryIt()">
            <ion-icon slot="start" name="play-outline" aria-hidden="true" />
            Probar
          </ion-button>
        </footer>
      </section>
    }

    <button
      type="button"
      class="pressable ms-auto flex size-11 items-center justify-center rounded-full border-0 bg-neutral-900 text-xl text-white shadow-lg ring-1 ring-black/10 dark:bg-white dark:text-neutral-900"
      [attr.aria-expanded]="open()"
      aria-controls="admin-menu"
      [attr.aria-label]="open() ? 'Cerrar el menú de administrador' : 'Menú de administrador'"
      [title]="open() ? 'Cerrar' : 'Menú de administrador'"
      (click)="open.set(!open())"
    >
      <ion-icon [name]="open() ? 'close-outline' : 'construct-outline'" aria-hidden="true" />
    </button>
  `,
})
export class AdminMenu {
  protected readonly transitions = inject(PageTransitionService);
  private readonly nav = inject(NavController);
  private readonly router = inject(Router);

  protected readonly open = signal(false);

  constructor() {
    addIcons({ closeOutline, constructOutline, playOutline });
  }

  /** Va a la sección siguiente del menú, igual que tocarla: así se ve la animación elegida. */
  protected tryIt(): void {
    const index = SECTIONS.findIndex((section) => this.router.url.startsWith(section.url));
    const next = SECTIONS[(index + 1) % SECTIONS.length];
    void this.nav.navigateRoot(next.url);
  }
}
