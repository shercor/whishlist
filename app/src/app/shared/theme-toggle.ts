import { Component, computed, inject } from '@angular/core';
import { IonButton, IonIcon } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { moonOutline, sunnyOutline } from 'ionicons/icons';
import { ThemeService } from '../core/theme/theme.service';

/** Botón suelto de modo claro/oscuro, para las pantallas sin menú (entrar y crear cuenta). */
@Component({
  selector: 'app-theme-toggle',
  imports: [IonButton, IonIcon],
  template: `
    <ion-button
      fill="clear"
      shape="round"
      color="medium"
      [title]="label()"
      [attr.aria-label]="label()"
      (click)="theme.toggle()"
    >
      <ion-icon
        slot="icon-only"
        [name]="theme.isDark() ? 'sunny-outline' : 'moon-outline'"
        aria-hidden="true"
      />
    </ion-button>
  `,
})
export class ThemeToggle {
  protected readonly theme = inject(ThemeService);
  protected readonly label = computed(() =>
    this.theme.isDark() ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro',
  );

  constructor() {
    addIcons({ moonOutline, sunnyOutline });
  }
}
