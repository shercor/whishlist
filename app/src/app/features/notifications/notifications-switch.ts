import { Component, inject, input } from '@angular/core';
import { IonIcon, NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { mailUnreadOutline, notificationsOutline } from 'ionicons/icons';
import { AuthService } from '../../core/auth/auth.service';

type Tab = 'avisos' | 'solicitudes';

/**
 * Las dos pestañas de Notificaciones: Avisos (lo que pasó) y Solicitudes (lo que espera tu
 * respuesta). Son dos pantallas con su propia dirección, pero una sola sección: así caben las
 * secciones en la barra inferior del teléfono. Cada pestaña lleva su contador.
 */
@Component({
  selector: 'app-notifications-switch',
  imports: [IonIcon],
  template: `
    <nav class="segmented" aria-label="Notificaciones">
      @for (tab of tabs; track tab.id) {
        <a
          class="control segmented-item pressable"
          [href]="tab.url"
          [attr.aria-current]="tab.id === current() ? 'page' : null"
          (click)="go($event, tab.id, tab.url)"
        >
          <ion-icon [name]="tab.icon" aria-hidden="true" />
          {{ tab.title }}
          @if (auth.counts()[tab.count]; as count) {
            <span class="segmented-count" [attr.aria-label]="count + ' por ver'">{{ count }}</span>
          }
        </a>
      }
    </nav>
  `,
})
export class NotificationsSwitch {
  protected readonly auth = inject(AuthService);
  private readonly nav = inject(NavController);

  readonly current = input.required<Tab>();

  protected readonly tabs = [
    {
      id: 'avisos' as Tab,
      url: '/avisos',
      title: 'Avisos',
      icon: 'notifications-outline',
      count: 'unread_notifications' as const,
    },
    {
      id: 'solicitudes' as Tab,
      url: '/solicitudes',
      title: 'Solicitudes',
      icon: 'mail-unread-outline',
      count: 'pending_requests' as const,
    },
  ];

  constructor() {
    addIcons({ mailUnreadOutline, notificationsOutline });
  }

  /** Cambiar de pestaña no es avanzar: sin animación de entrar y sin sumar al historial. */
  protected async go(event: MouseEvent, tab: Tab, url: string): Promise<void> {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
      return;
    }
    event.preventDefault();
    if (tab !== this.current()) {
      await this.nav.navigateRoot(url, { animated: false, replaceUrl: true });
    }
  }
}
