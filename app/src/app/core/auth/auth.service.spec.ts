import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { AuthService, TOKEN_KEY } from './auth.service';

describe('AuthService', () => {
  let backend: HttpTestingController;

  /** El servicio lee el token guardado al crearse, así que se crea después de preparar localStorage. */
  function createService(): AuthService {
    return TestBed.inject(AuthService);
  }

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  it('entra con correo y contraseña y guarda el token de la API', async () => {
    const auth = createService();

    const login = auth.login('ana@whishlist.test', 'password');
    const request = backend.expectOne(`${environment.apiUrl}/tokens`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({
      email: 'ana@whishlist.test',
      password: 'password',
      device_name: 'Navegador web',
    });
    request.flush({
      token: '1|token-nuevo',
      user: {
        username: 'ana',
        handle: '@ana',
        display_name: 'Ana',
        avatar: null,
        initials: 'A',
        email: 'ana@whishlist.test',
      },
    });
    await login;

    expect(auth.isAuthenticated()).toBe(true);
    expect(auth.user()?.username).toBe('ana');
    expect(localStorage.getItem(TOKEN_KEY)).toBe('1|token-nuevo');
  });

  it('si la contraseña no coincide no guarda nada', async () => {
    const auth = createService();

    const login = auth.login('ana@whishlist.test', 'mala');
    backend
      .expectOne(`${environment.apiUrl}/tokens`)
      .flush(
        { errors: { email: ['Ese correo y esa contraseña no coinciden.'] } },
        { status: 422, statusText: 'Unprocessable Content' },
      );

    await expect(login).rejects.toBeTruthy();
    expect(auth.isAuthenticated()).toBe(false);
    expect(localStorage.getItem(TOKEN_KEY)).toBeNull();
  });

  it('al cerrar sesión olvida el token aunque la API no responda', async () => {
    localStorage.setItem(TOKEN_KEY, '1|token-viejo');
    const auth = createService();

    const logout = auth.logout();
    backend.expectOne(`${environment.apiUrl}/tokens/current`).error(new ProgressEvent('error'));

    await expect(logout).rejects.toBeTruthy();
    expect(auth.isAuthenticated()).toBe(false);
    expect(localStorage.getItem(TOKEN_KEY)).toBeNull();
  });
});
