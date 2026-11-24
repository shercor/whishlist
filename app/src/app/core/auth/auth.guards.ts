import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/**
 * Páginas que requieren sesión. Qué datos ve cada uno lo decide la API.
 * Sin sesión manda a entrar y recuerda adónde ibas: un enlace secreto abierto
 * sin sesión sigue funcionando después de entrar o crear la cuenta.
 */
export const authGuard: CanActivateFn = (_route, state) =>
  inject(AuthService).isAuthenticated() ||
  inject(Router).createUrlTree(['/login'], {
    queryParams: state.url === '/listas' ? {} : { volver: state.url },
  });

/** El login no se muestra a quien ya entró. */
export const guestGuard: CanActivateFn = () =>
  !inject(AuthService).isAuthenticated() || inject(Router).createUrlTree(['/listas']);
