import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { globeOutline, lockClosedOutline } from 'ionicons/icons';
import { WishlistVisibility } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { VISIBILITIES, visibilityOf } from '../../shared/visibility';

/** Crear una lista. Editarla usará el mismo formulario cuando exista esa pantalla. */
@Component({
  selector: 'app-wishlist-form',
  imports: [
    RouterLink,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './wishlist-form.page.html',
})
export class WishlistFormPage {
  private readonly api = inject(WishlistsService);
  private readonly router = inject(Router);

  protected readonly name = signal('');
  protected readonly description = signal('');
  protected readonly eventDate = signal('');
  /** Privada por defecto, igual que en la web: abrirla es una decisión del dueño. */
  protected readonly visibility = signal<WishlistVisibility>('privada');

  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly canSave = computed(() => this.name().trim() !== '' && !this.saving());

  protected readonly visibilities = VISIBILITIES;
  protected readonly chosen = computed(() => visibilityOf(this.visibility()));

  constructor() {
    addIcons({ globeOutline, lockClosedOutline });
  }

  protected async submit(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSave()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);

    try {
      await this.api.create({
        name: this.name().trim(),
        description: this.description().trim() || null,
        visibility: this.visibility(),
        event_date: this.eventDate() || null,
      });
      await this.router.navigateByUrl('/listas', { replaceUrl: true });
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo crear la lista. Intenta de nuevo.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement | HTMLTextAreaElement).value;
  }
}
