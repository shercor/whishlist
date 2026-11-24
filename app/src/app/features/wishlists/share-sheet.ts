import { Component, inject, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonIcon } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { chevronForward, closeOutline, copyOutline, peopleOutline } from 'ionicons/icons';
import { Notify } from '../../shared/notify';

/**
 * Compartir una lista mía: el enlace para copiar y, si es privada, el paso a «Quién la ve».
 * Vive en una hoja y no en la pantalla de la lista, para que ahí lo primero sean los regalos.
 */
@Component({
  selector: 'app-share-sheet',
  imports: [RouterLink, IonButton, IonIcon],
  template: `
    <div class="modal-body p-4 md:p-5">
      <div class="mb-3 flex items-start justify-between gap-3">
        <h2 class="m-0 text-lg font-semibold">Compartir la lista</h2>
        <ion-button
          fill="clear"
          color="medium"
          class="-mt-1 -mr-2 shrink-0"
          (click)="closed.emit()"
          aria-label="Cerrar"
        >
          <ion-icon slot="icon-only" name="close-outline" />
        </ion-button>
      </div>

      <h3 class="section-title">Enlace para compartir</h3>
      <div class="flex gap-2">
        <input
          type="text"
          class="control min-w-0 flex-1"
          readonly
          aria-label="Enlace para compartir"
          [value]="link()"
          (focus)="$any($event.target).select()"
        />
        <button type="button" class="control pressable shrink-0" (click)="copy()">
          <ion-icon name="copy-outline" aria-hidden="true" />
          Copiar
        </button>
      </div>
      <p class="hint mt-2 mb-0">
        Quien lo tenga entra aunque no te siga, y queda anotado en «Quién la ve».
      </p>

      @if (isPrivate()) {
        <a
          class="tappable-row -mx-2 mt-4 flex items-center gap-3 rounded-lg px-2 py-2.5 text-inherit no-underline"
          [routerLink]="['/listas', wishlistId(), 'acceso']"
          (click)="closed.emit()"
        >
          <ion-icon
            name="people-outline"
            aria-hidden="true"
            class="shrink-0 text-xl text-neutral-500"
          />
          <span class="min-w-0 flex-1">
            <span class="block font-medium">Quién la ve</span>
            <span class="block text-sm text-neutral-500 dark:text-neutral-400">
              Quién entró, quién te la pidió y a qué seguidor más dársela.
            </span>
          </span>
          <ion-icon name="chevron-forward" aria-hidden="true" class="shrink-0 text-neutral-400" />
        </a>
      }
    </div>
  `,
})
export class ShareSheet {
  private readonly notify = inject(Notify);

  readonly link = input.required<string>();
  readonly wishlistId = input.required<number>();
  readonly isPrivate = input(false);
  readonly closed = output<void>();

  constructor() {
    addIcons({ chevronForward, closeOutline, copyOutline, peopleOutline });
  }

  protected async copy(): Promise<void> {
    try {
      await navigator.clipboard.writeText(this.link());
      await this.notify.toast('Enlace copiado.');
    } catch {
      await this.notify.toast('No se pudo copiar. Selecciona el enlace y cópialo a mano.');
    }
  }
}
