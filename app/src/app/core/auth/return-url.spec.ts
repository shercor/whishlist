import { returnUrl } from './return-url';

describe('returnUrl', () => {
  it('vuelve a la página de la app que se quería abrir', () => {
    expect(returnUrl('/s/abc123')).toBe('/s/abc123');
    expect(returnUrl('/regalar/9?x=1')).toBe('/regalar/9?x=1');
  });

  it('sin nada, o con algo que saldría de la app, lleva a Mis listas', () => {
    expect(returnUrl(null)).toBe('/listas');
    expect(returnUrl('')).toBe('/listas');
    expect(returnUrl('https://otro.sitio/robar')).toBe('/listas');
    expect(returnUrl('//otro.sitio')).toBe('/listas');
    expect(returnUrl('/\\otro.sitio')).toBe('/listas');
    expect(returnUrl('/login')).toBe('/listas');
  });
});
