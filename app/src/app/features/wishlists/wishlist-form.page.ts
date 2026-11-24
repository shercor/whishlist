import { Component, computed, inject, input, signal } from '@angular/core';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSkeletonText,
  IonSpinner,
  IonTitle,
  IonToolbar,
  NavController,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { globeOutline, lockClosedOutline } from 'ionicons/icons';
import { WishlistVisibility } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { Notify } from '../../shared/notify';
import { VISIBILITIES, visibilityOf } from '../../shared/visibility';

/** Crear una lista, o editarla si la ruta trae su :id. */
@Component({
  selector: 'app-wishlist-form',
  imports: [
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonSkeletonText,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './wishlist-form.page.html',
})
export class WishlistFormPage implements ViewWillEnter {
  private readonly api = inject(WishlistsService);
  private readonly nav = inject(NavController);
  private readonly notify = inject(Notify);

  /** El :id de la ruta al editar; sin él, se crea una lista nueva. */
  readonly id = input<string>();
  protected readonly editing = computed(() => this.id() !== undefined);

  protected readonly name = signal('');
  protected readonly description = signal('');
  protected readonly eventDate = signal('');
  /** Privada por defecto, igual que en la web: abrirla es una decisión del dueño. */
  protected readonly visibility = signal<WishlistVisibility>('privada');
  /** El nombre guardado, para el título: no cambia mientras se escribe. */
  protected readonly savedName = signal('');

  protected readonly loading = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly canSave = computed(
    () => this.name().trim() !== '' && !this.saving() && !this.loading(),
  );

  protected readonly visibilities = VISIBILITIES;
  protected readonly chosen = computed(() => visibilityOf(this.visibility()));

  constructor() {
    addIcons({ globeOutline, lockClosedOutline });
  }

  async ionViewWillEnter(): Promise<void> {
    const id = this.id();
    if (id === undefined) {
      return;
    }

    this.loading.set(true);
    try {
      const wishlist = await this.api.show(Number(id));
      this.name.set(wishlist.name);
      this.savedName.set(wishlist.name);
      this.description.set(wishlist.description ?? '');
      this.eventDate.set(wishlist.event_date ?? '');
      this.visibility.set(wishlist.visibility);
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo cargar la lista.'));
    } finally {
      this.loading.set(false);
    }
  }

  protected async submit(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSave()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    const input = {
      name: this.name().trim(),
      description: this.description().trim() || null,
      visibility: this.visibility(),
      event_date: this.eventDate() || null,
    };

    try {
      const id = this.id();
      if (id === undefined) {
        const created = await this.api.create(input);
        await this.notify.toast('Lista creada. Ahora agrégale regalos.');
        await this.nav.navigateForward(['/listas', created.id], { replaceUrl: true });
      } else {
        await this.api.update(Number(id), input);
        await this.notify.toast('Lista guardada.');
        await this.nav.navigateBack(['/listas', id]);
      }
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo guardar la lista. Intenta de nuevo.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected async remove(): Promise<void> {
    const id = this.id();
    if (id === undefined) {
      return;
    }

    const confirmed = await this.notify.confirm(
      `¿Eliminar «${this.savedName()}» y todos sus regalos?`,
      '',
      'Eliminar',
    );
    if (!confirmed) {
      return;
    }

    this.saving.set(true);
    try {
      await this.api.remove(Number(id));
      await this.notify.toast('Lista eliminada.');
      await this.nav.navigateRoot('/listas');
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo eliminar la lista.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement | HTMLTextAreaElement).value;
  }
}
