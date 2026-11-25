import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import {
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonSpinner,
  IonTitle,
  IonToolbar,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { linkOutline } from 'ionicons/icons';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { UserMenu } from '../../shared/user-menu';

/**
 * El enlace secreto de una lista (`/s/:token`): lo abre y lleva a la lista.
 * Sin sesión, el guard manda antes a entrar y después vuelve acá.
 */
@Component({
  selector: 'app-shared-link',
  imports: [
    UserMenu,
    RouterLink,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonMenuButton,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  template: `
    <ion-header>
      <ion-toolbar>
        <ion-buttons slot="start">
          <ion-menu-button />
        </ion-buttons>
        <ion-title><span class="title-gift" aria-hidden="true"></span><span class="sr-only">Lista compartida</span></ion-title>
        <ion-buttons slot="end"><app-user-menu /></ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="gift-background" [fullscreen]="true">
      <main class="mx-auto w-full max-w-xl px-4 py-5 md:px-6 md:py-8">
        @if (error(); as message) {
          <div
            role="alert"
            class="card enter-up flex flex-col items-center gap-2 px-6 py-10 text-center"
          >
            <div
              aria-hidden="true"
              class="flex size-12 items-center justify-center rounded-full bg-neutral-100 text-2xl text-neutral-600 dark:bg-white/10 dark:text-neutral-300"
            >
              <ion-icon name="link-outline" />
            </div>
            <h1 class="mt-2 mb-0 text-lg font-semibold">Este enlace no abre ninguna lista</h1>
            <p class="m-0 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
              {{ message }}
            </p>
            <ion-button class="pressable mt-3" routerLink="/listas">Ir a mis listas</ion-button>
          </div>
        } @else {
          <div
            role="status"
            class="flex flex-col items-center gap-3 py-16 text-sm text-neutral-500 dark:text-neutral-400"
          >
            <ion-spinner name="crescent" aria-hidden="true" />
            Abriendo la lista…
          </div>
        }
      </main>
    </ion-content>
  `,
})
export class SharedLinkPage implements ViewWillEnter {
  private readonly api = inject(WishlistsService);
  private readonly router = inject(Router);

  readonly token = input.required<string>();

  protected readonly error = signal<string | null>(null);

  constructor() {
    addIcons({ linkOutline });
  }

  ionViewWillEnter(): void {
    void this.open();
  }

  private async open(): Promise<void> {
    this.error.set(null);
    try {
      const wishlist = await this.api.openByLink(this.token());
      // replaceUrl: «Volver» desde la lista no debe pasar de nuevo por el enlace.
      await this.router.navigate([wishlist.is_mine ? '/listas' : '/regalar', wishlist.id], {
        replaceUrl: true,
      });
    } catch (error) {
      this.error.set(
        error instanceof HttpErrorResponse && error.status === 404
          ? 'Puede que esté mal copiado, o que su dueño lo haya cambiado. Pídele el enlace de nuevo.'
          : errorMessage(error, 'No se pudo abrir. Intenta de nuevo.'),
      );
    }
  }
}
