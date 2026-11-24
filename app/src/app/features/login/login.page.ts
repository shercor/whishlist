import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline } from 'ionicons/icons';
import { AuthService } from '../../core/auth/auth.service';
import { returnUrl } from '../../core/auth/return-url';
import { errorMessage } from '../../core/http/api-error';

@Component({
  selector: 'app-login',
  imports: [RouterLink, IonButton, IonContent, IonIcon, IonSpinner],
  templateUrl: './login.page.html',
  styleUrl: './login.page.scss',
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly email = signal('');
  protected readonly password = signal('');
  protected readonly signingIn = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly canSubmit = computed(
    () => this.email().trim() !== '' && this.password() !== '' && !this.signingIn(),
  );

  constructor() {
    addIcons({ giftOutline });
  }

  protected async submit(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSubmit()) {
      return;
    }

    this.signingIn.set(true);
    this.error.set(null);

    try {
      await this.auth.login(this.email().trim(), this.password());
      await this.router.navigateByUrl(returnUrl(this.route.snapshot.queryParamMap.get('volver')), {
        replaceUrl: true,
      });
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo iniciar sesión. Intenta de nuevo.'));
    } finally {
      this.signingIn.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }
}
