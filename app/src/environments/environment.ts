/**
 * Configuración de producción: la app y la API se sirven desde el mismo dominio.
 */
export const environment = {
  apiUrl: '/api/v1',
  /** Enlace secreto de una lista privada: lo abre la pantalla `/s/:token` de la app. */
  shareUrl: '/s/',
};
