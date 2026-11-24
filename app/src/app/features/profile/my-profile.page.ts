import { Component, computed, inject, linkedSignal, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
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
import { imageOutline } from 'ionicons/icons';
import { MeService } from '../../core/api/me.service';
import { User } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { Notify } from '../../shared/notify';

/** Con qué me encuentran los demás, y cuánto de mí ven. */
@Component({
  selector: 'app-my-profile',
  imports: [
    RouterLink,
    Avatar,
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
  templateUrl: './my-profile.page.html',
})
export class MyProfilePage implements ViewWillEnter {
  private readonly api = inject(MeService);
  protected readonly auth = inject(AuthService);
  private readonly notify = inject(Notify);

  /** El formulario parte de lo guardado y se reinicia si el perfil cambia por fuera. */
  protected readonly name = linkedSignal(() => this.auth.user()?.name ?? '');
  protected readonly username = linkedSignal(() => this.auth.user()?.username ?? '');
  protected readonly showName = linkedSignal(() => this.auth.user()?.show_name ?? false);
  protected readonly isPublic = linkedSignal(() => !(this.auth.user()?.is_private ?? true));

  protected readonly saving = signal(false);
  protected readonly avatarBusy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly canSave = computed(
    () => !this.saving() && this.name().trim() !== '' && this.username().trim() !== '',
  );

  constructor() {
    addIcons({ imageOutline });
  }

  ionViewWillEnter(): void {
    void this.auth.refresh();
  }

  protected async save(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSave()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    try {
      const user = await this.api.update({
        name: this.name().trim(),
        username: this.username().trim(),
        show_name: this.showName(),
        is_private: !this.isPublic(),
      });
      this.auth.setUser(user);
      await this.notify.toast('Perfil guardado.');
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo guardar. Intenta de nuevo.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected async pickAvatar(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
      return;
    }
    await this.changeAvatar(() => this.api.setAvatar(file), 'Foto cambiada.');
  }

  protected async removeAvatar(): Promise<void> {
    const confirmed = await this.notify.confirm(
      '¿Quitar tu foto?',
      'Vuelven a verse tus iniciales.',
      'Quitar',
    );
    if (confirmed) {
      await this.changeAvatar(() => this.api.removeAvatar(), 'Foto quitada.');
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  protected checkedOf(event: Event): boolean {
    return (event.target as HTMLInputElement).checked;
  }

  private async changeAvatar(action: () => Promise<User>, done: string): Promise<void> {
    this.avatarBusy.set(true);
    try {
      this.auth.setUser(await action());
      await this.notify.toast(done);
    } catch (error) {
      await this.notify.toast(errorMessage(error, 'No se pudo cambiar la foto.'));
    } finally {
      this.avatarBusy.set(false);
    }
  }
}
