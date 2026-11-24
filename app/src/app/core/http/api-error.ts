import { HttpErrorResponse } from '@angular/common/http';

/**
 * Un mensaje para el usuario a partir de un error de la API.
 * Los errores técnicos no se muestran tal cual: caen en el mensaje genérico.
 */
export function errorMessage(
  error: unknown,
  fallback = 'Algo salió mal. Intenta de nuevo.',
): string {
  if (error instanceof HttpErrorResponse) {
    if (error.status === 0) {
      return 'No hay conexión con el servidor.';
    }

    if (error.status === 429) {
      return 'Demasiados intentos. Espera un minuto y prueba de nuevo.';
    }

    // Los 422 de la API traen mensajes en español pensados para el usuario.
    const firstFieldError = Object.values(error.error?.errors ?? {}).flat()[0];
    if (error.status === 422 && typeof firstFieldError === 'string') {
      return firstFieldError;
    }
  }

  return fallback;
}
