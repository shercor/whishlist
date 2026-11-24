import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { compassOutline } from 'ionicons/icons';

/** Una dirección que no es ninguna pantalla de la app. */
@Component({
  selector: 'app-not-found',
  imports: [
    RouterLink,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonMenuButton,
    IonTitle,
    IonToolbar,
  ],
  template: `
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-menu-button />
        </ion-buttons>
        <ion-title>No existe</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content>
      <main class="mx-auto w-full max-w-xl px-4 py-5 md:px-6 md:py-8">
        <div class="card enter-up flex flex-col items-center gap-2 px-6 py-10 text-center">
          <div
            aria-hidden="true"
            class="flex size-12 items-center justify-center rounded-full bg-neutral-100 text-2xl text-neutral-600 dark:bg-white/10 dark:text-neutral-300"
          >
            <ion-icon name="compass-outline" />
          </div>
          <h1 class="mt-2 mb-0 text-lg font-semibold">Esta página no existe</h1>
          <p class="m-0 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
            Puede que la dirección esté mal escrita o que el enlace sea de una versión anterior.
          </p>
          <ion-button class="pressable mt-3" routerLink="/listas">Ir a mis listas</ion-button>
        </div>
      </main>
    </ion-content>
  `,
})
export class NotFoundPage {
  constructor() {
    addIcons({ compassOutline });
  }
}
