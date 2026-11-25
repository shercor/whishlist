import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonSpinner } from '@ionic/angular';
import { AuthService } from '../../core/auth/auth.service';
import { ThemeService } from '../../core/theme/theme.service';
import { returnUrl } from '../../core/auth/return-url';
import { errorMessage } from '../../core/http/api-error';
import { ThemeToggle } from '../../shared/theme-toggle';

/** Crear una cuenta. Sin verificar el correo: al terminar quedas dentro, como en la web. */
@Component({
  selector: 'app-register',
  imports: [RouterLink, ThemeToggle, IonButton, IonContent, IonSpinner],
  templateUrl: './register.page.html',
  styleUrl: '../login/login.page.scss',
})
export class RegisterPage {
  private readonly auth = inject(AuthService);
  /** Para elegir la versión del logo que se lee sobre el fondo de cada modo. */
  protected readonly theme = inject(ThemeService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly name = signal('');
  protected readonly username = signal('');
  protected readonly email = signal('');
  protected readonly password = signal('');
  protected readonly confirmation = signal('');
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Se avisa antes de enviar: es el error más común y se ve al escribir. */
  protected readonly mismatch = computed(
    () => this.confirmation() !== '' && this.confirmation() !== this.password(),
  );
  protected readonly canSubmit = computed(
    () =>
      this.name().trim() !== '' &&
      this.username().trim() !== '' &&
      this.email().trim() !== '' &&
      this.password() !== '' &&
      this.confirmation() !== '' &&
      !this.mismatch() &&
      !this.saving(),
  );

  protected async submit(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSubmit()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    try {
      await this.auth.register({
        name: this.name().trim(),
        username: this.username().trim(),
        email: this.email().trim(),
        password: this.password(),
        password_confirmation: this.confirmation(),
      });
      await this.router.navigateByUrl(returnUrl(this.route.snapshot.queryParamMap.get('volver')), {
        replaceUrl: true,
      });
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo crear la cuenta. Intenta de nuevo.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }
}
