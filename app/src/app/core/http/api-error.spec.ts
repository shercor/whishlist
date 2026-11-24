import { HttpErrorResponse } from '@angular/common/http';
import { errorMessage } from './api-error';

describe('errorMessage', () => {
  it('muestra el primer error de validación de la API', () => {
    const error = new HttpErrorResponse({
      status: 422,
      error: {
        message: 'Ese correo y esa contraseña no coinciden. (y 1 error más)',
        errors: { email: ['Ese correo y esa contraseña no coinciden.'], otro: ['Otro error.'] },
      },
    });

    expect(errorMessage(error)).toBe('Ese correo y esa contraseña no coinciden.');
  });

  it('distingue la falta de conexión', () => {
    expect(errorMessage(new HttpErrorResponse({ status: 0 }))).toBe(
      'No hay conexión con el servidor.',
    );
  });

  it('explica el freno de intentos de la API', () => {
    expect(errorMessage(new HttpErrorResponse({ status: 429 }))).toBe(
      'Demasiados intentos. Espera un minuto y prueba de nuevo.',
    );
  });

  it('no muestra errores técnicos: usa el mensaje genérico', () => {
    expect(errorMessage(new HttpErrorResponse({ status: 500 }), 'No se pudo.')).toBe('No se pudo.');
    expect(errorMessage(new TypeError('Cannot read properties of undefined'))).toBe(
      'Algo salió mal. Intenta de nuevo.',
    );
  });
});
