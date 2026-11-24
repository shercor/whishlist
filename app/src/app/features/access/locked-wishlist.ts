import { Component, inject, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { lockClosedOutline } from 'ionicons/icons';
import { AccessService } from '../../core/api/access.service';
import { LockedWishlist } from '../../core/api/models';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { Notify } from '../../shared/notify';

/**
 * Una lista privada que no puedo ver: de quién es y, si se puede, pedírsela.
 * A propósito no dice su nombre: probando ids se sabría qué listas tiene cada persona.
 */
@Component({
  selector: 'app-locked-wishlist',
  imports: [RouterLink, Avatar, IonButton, IonIcon, IonSpinner],
  template: `
    <div
      class="card enter-up mx-auto flex max-w-xl flex-col items-center gap-2 px-6 py-10 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-neutral-100 text-2xl text-neutral-600 dark:bg-white/10 dark:text-neutral-300"
      >
        <ion-icon name="lock-closed-outline" aria-hidden="true" />
      </div>
      <h1 class="mt-2 mb-0 text-lg font-semibold">Esta lista es privada</h1>
      <a
        class="inline-flex items-center gap-2 text-sm text-inherit no-underline"
        [routerLink]="['/u', locked().owner.username]"
      >
        <app-avatar [user]="locked().owner" class="size-6 text-[0.6rem]" />
        <span class="text-neutral-500 dark:text-neutral-400">
          <span class="font-medium text-inherit">{{ locked().owner.display_name }}</span>
          decide quién la ve.
        </span>
      </a>

      @switch (locked().access_status) {
        @case ('pendiente') {
          <p class="m-0 mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
            Ya le pediste acceso. Cuando responda lo verás en
            <a routerLink="/solicitudes">Solicitudes</a>.
          </p>
        }
        @case ('rechazado') {
          <p class="m-0 mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
            {{ locked().owner.display_name }} no te dio acceso.
          </p>
        }
        @case ('revocado') {
          <p class="m-0 mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
            {{ locked().owner.display_name }} te quitó el acceso.
          </p>
        }
        @default {
          @if (locked().can_request) {
            <form
              class="mt-3 flex w-full max-w-sm flex-col gap-3 text-left"
              (submit)="request($event)"
            >
              <label class="flex flex-col gap-1.5 text-sm font-medium">
                <span>
                  Cuéntale quién eres
                  <span class="font-normal text-neutral-500 dark:text-neutral-400">(opcional)</span>
                </span>
                <input
                  type="text"
                  class="control w-full"
                  maxlength="300"
                  placeholder="Soy Bruno, tu primo."
                  [value]="message()"
                  (input)="message.set(valueOf($event))"
                />
              </label>
              @if (error(); as text) {
                <p role="alert" class="m-0 text-sm text-red-600 dark:text-red-400">{{ text }}</p>
              }
              <ion-button type="submit" class="pressable m-0 self-end" [disabled]="busy()">
                @if (busy()) {
                  <ion-spinner name="crescent" aria-label="Enviando" />
                } @else {
                  Pedir acceso
                }
              </ion-button>
            </form>
          } @else {
            <p class="m-0 mt-2 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
              Para pedírsela tienes que seguir a {{ locked().owner.display_name }}. O pídele el
              enlace de la lista.
            </p>
            <ion-button
              fill="outline"
              class="pressable mt-3"
              [routerLink]="['/u', locked().owner.username]"
            >
              Ver su perfil
            </ion-button>
          }
        }
      }
    </div>
  `,
})
export class LockedWishlistView {
  private readonly api = inject(AccessService);
  private readonly notify = inject(Notify);

  readonly wishlistId = input.required<number>();
  readonly locked = input.required<LockedWishlist>();
  /** Se pidió: la pantalla vuelve a preguntar, y ahora dice «Ya le pediste acceso». */
  readonly requested = output<void>();

  protected readonly message = signal('');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  constructor() {
    addIcons({ lockClosedOutline });
  }

  protected async request(event: Event): Promise<void> {
    event.preventDefault();
    this.busy.set(true);
    this.error.set(null);
    try {
      await this.api.request(this.wishlistId(), this.message().trim() || null);
      await this.notify.toast(`Listo. Le avisamos a ${this.locked().owner.display_name}.`);
      this.requested.emit();
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo pedir. Intenta de nuevo.'));
    } finally {
      this.busy.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }
}
