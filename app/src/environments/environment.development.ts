/**
 * Configuración de desarrollo (ng serve). Los puertos son los del .env de la raíz: HTTP_PORT
 * para la API y WEB_PORT para la app.
 */
export const environment = {
  apiUrl: 'http://localhost:8080/api/v1',
  /** Enlace secreto de una lista privada: lo abre la pantalla `/s/:token` de la app. */
  shareUrl: 'http://localhost:4202/s/',
};
