import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../core/auth/auth.service';

/**
 * Cerrar sesión y llevar a la pantalla de entrar. Se llama en el constructor (o como inicializador
 * de un campo) de quien lo use: el menú de la cuenta y Mi perfil. Un solo lugar, para que los dos
 * salgan igual.
 */
export function injectSignOut(): () => Promise<void> {
  const auth = inject(AuthService);
  const router = inject(Router);

  return async () => {
    try {
      await auth.logout();
    } catch {
      // La sesión local ya se borró: el token que quedó en la API vence solo.
    }
    await router.navigateByUrl('/login', { replaceUrl: true });
  };
}
