/**
 * Configuración de desarrollo (ng serve). El puerto es el HTTP_PORT del .env de la raíz.
 */
export const environment = {
  apiUrl: 'http://localhost:8080/api/v1',
  /** Enlace secreto de una lista privada. Lo abre la web Blade mientras la app no tenga esa entrada. */
  shareUrl: 'http://localhost:8080/s/',
};
