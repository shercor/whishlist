import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../auth/auth.service';

/**
 * Agrega el token de Sanctum a las peticiones a la API. Si la API responde 401
 * (token revocado o vencido), cierra la sesión local y vuelve al login.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  // El token nunca sale hacia otros servidores.
  if (!req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();
  const request = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && token) {
        auth.clear();
        void router.navigateByUrl('/login');
      }

      return throwError(() => error);
    }),
  );
};
