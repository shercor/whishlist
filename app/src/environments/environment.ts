/**
 * Configuración de producción: la app y la API se sirven desde el mismo dominio.
 */
export const environment = {
  apiUrl: '/api/v1',
  /** Enlace secreto de una lista privada. Lo abre la web Blade mientras la app no tenga esa entrada. */
  shareUrl: '/s/',
};
