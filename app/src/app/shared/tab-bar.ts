import { Component, computed, inject, linkedSignal } from '@angular/core';
import { IonIcon, NavController } from '@ionic/angular';
import { AuthService } from '../core/auth/auth.service';
import { SECTIONS, Section, injectCurrentPath, sectionCount, sectionFor } from './navigation';

/**
 * La barra inferior del teléfono, como la de Tenpo: las cinco secciones a un toque del pulgar, la
 * actual con la barrita encendida en su borde de arriba (la misma del menú lateral, girada) y
 * su ícono y texto en ámbar. La barrita es una sola para toda la barra y se desliza hasta la
 * sección elegida, en vez de apagarse en una y encenderse en la otra. En escritorio no se ve: ahí está el menú lateral (tailwind.css).
 *
 * La pone app.ts en todas las pantallas con sesión. En las de más adentro (una lista, editar)
 * ninguna sección queda marcada y la barrita se apaga.
 */
@Component({
  selector: 'app-tab-bar',
  imports: [IonIcon],
  template: `
    <nav class="tab-bar" aria-label="Secciones" [style.--tab-index]="barIndex()">
      <span class="tab-bar-indicator" [class.is-hidden]="activeIndex() < 0" aria-hidden="true"></span>
      @for (section of sections; track section.url) {
        @let current = section === active();
        <a
          class="tab-bar-item pressable"
          [href]="section.url"
          [attr.aria-current]="current ? 'page' : null"
          (click)="go($event, section)"
        >
          <span class="tab-bar-icon">
            <ion-icon [name]="section.icon" aria-hidden="true" />
            @if (countOf(section); as count) {
              <span class="tab-bar-badge" [attr.aria-label]="count + ' por ver'">{{
                count > 99 ? '99+' : count
              }}</span>
            }
          </span>
          <span class="tab-bar-label">{{ section.title }}</span>
        </a>
      }
    </nav>
  `,
})
export class TabBar {
  private readonly auth = inject(AuthService);
  private readonly nav = inject(NavController);
  private readonly path = injectCurrentPath();

  protected readonly sections = SECTIONS;
  protected readonly active = computed(() => sectionFor(this.path()));
  /** La posición de la sección actual, para mover la barrita; -1 si la ruta no es de ninguna. */
  protected readonly activeIndex = computed(() => {
    const active = this.active();
    return active ? this.sections.indexOf(active) : -1;
  });
  /**
   * Dónde está la barrita: la sección actual o, en una pantalla de más adentro, la última que lo
   * fue, para que se apague en su lugar en vez de deslizarse a la primera mientras desaparece.
   */
  protected readonly barIndex = linkedSignal<number, number>({
    source: this.activeIndex,
    computation: (index, previous) => (index >= 0 ? index : (previous?.value ?? 0)),
  });

  protected countOf(section: Section): number {
    return sectionCount(section, this.auth.counts());
  }

  /**
   * Cambiar de sección no es avanzar: la nueva pasa a ser la raíz, con la animación elegida en el
   * menú de administrador, igual que el menú lateral en escritorio. El enlace sigue siendo un <a> de verdad, para que se
   * pueda abrir en otra pestaña o copiar.
   */
  protected async go(event: MouseEvent, section: Section): Promise<void> {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
      return;
    }
    event.preventDefault();
    if (section !== this.active()) {
      await this.nav.navigateRoot(section.url);
    }
  }
}
