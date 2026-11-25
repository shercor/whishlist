import { Component, computed, inject } from '@angular/core';
import { IonIcon, NavController } from '@ionic/angular';
import { AuthService } from '../core/auth/auth.service';
import { SECTIONS, Section, injectCurrentPath, sectionCount, sectionFor } from './navigation';

/**
 * La barra inferior del teléfono, como la de Tenpo: las cinco secciones a un toque del pulgar, la
 * actual con la barrita encendida en su borde de arriba (la misma del menú lateral, girada) y
 * su ícono y texto en ámbar. En escritorio no se ve: ahí está el menú lateral (tailwind.css).
 *
 * La pone app.ts solo en las pantallas de primer nivel; en las de más adentro se esconde.
 */
@Component({
  selector: 'app-tab-bar',
  imports: [IonIcon],
  template: `
    <nav class="tab-bar" aria-label="Secciones">
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

  protected countOf(section: Section): number {
    return sectionCount(section, this.auth.counts());
  }

  /**
   * Cambiar de sección no es avanzar: la nueva pasa a ser la raíz, sin la animación de entrar a
   * una pantalla (como cambiar de pestaña). El enlace sigue siendo un <a> de verdad, para que se
   * pueda abrir en otra pestaña o copiar.
   */
  protected async go(event: MouseEvent, section: Section): Promise<void> {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
      return;
    }
    event.preventDefault();
    if (section !== this.active()) {
      await this.nav.navigateRoot(section.url, { animated: false });
    }
  }
}
