/**
 * Los avisos guardan el enlace a la pantalla de la web (`/gifts/5`, `/access`...). Esto lo
 * traduce a la ruta de la app que muestra lo mismo, o null si la app no tiene esa pantalla.
 */
const ROUTES: [RegExp, (match: RegExpMatchArray) => string][] = [
  [/^\/gifts\/(\d+)$/, (m) => `/regalar/${m[1]}`],
  [/^\/wishlists\/(\d+)\/accesos$/, (m) => `/listas/${m[1]}/acceso`],
  [/^\/wishlists\/(\d+)$/, (m) => `/listas/${m[1]}`],
  [/^\/u\/([^/]+)$/, (m) => `/u/${m[1]}`],
  [/^\/reservations$/, () => '/reservas'],
  [/^\/access$/, () => '/solicitudes'],
  [/^\/seguidores$/, () => '/mi-gente'],
];

export function appPathFor(url: string | null): string | null {
  if (!url) {
    return null;
  }

  const path = new URL(url, 'http://localhost').pathname.replace(/\/$/, '');
  for (const [pattern, toApp] of ROUTES) {
    const match = path.match(pattern);
    if (match) {
      return toApp(match);
    }
  }
  return null;
}
