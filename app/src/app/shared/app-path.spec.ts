import { appPathFor } from './app-path';

describe('appPathFor', () => {
  it('lleva cada aviso a la pantalla de la app que muestra lo mismo', () => {
    expect(appPathFor('http://localhost:8080/gifts/15')).toBe('/regalar/15');
    expect(appPathFor('http://localhost:8080/wishlists/2/accesos')).toBe('/listas/2/acceso');
    expect(appPathFor('http://localhost:8080/wishlists/2')).toBe('/listas/2');
    expect(appPathFor('http://localhost:8080/u/camila_diaz3')).toBe('/u/camila_diaz3');
    expect(appPathFor('http://localhost:8080/reservations')).toBe('/reservas');
    expect(appPathFor('https://whishlist.cl/access')).toBe('/solicitudes');
    expect(appPathFor('/seguidores')).toBe('/mi-gente');
  });

  it('sin una pantalla equivalente no lleva a ninguna parte', () => {
    expect(appPathFor('http://localhost:8080/algo-nuevo')).toBeNull();
    expect(appPathFor(null)).toBeNull();
  });
});
