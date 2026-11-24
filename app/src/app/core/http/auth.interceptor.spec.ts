import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService, TOKEN_KEY } from '../auth/auth.service';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  let http: HttpClient;
  let backend: HttpTestingController;

  beforeEach(() => {
    localStorage.setItem(TOKEN_KEY, '1|token-de-ana');
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    backend.verify();
    localStorage.clear();
  });

  it('manda el token a la API y nunca a otros servidores', () => {
    http.get(`${environment.apiUrl}/me`).subscribe();
    http.get('https://otro-servidor.example/algo').subscribe();

    expect(backend.expectOne(`${environment.apiUrl}/me`).request.headers.get('Authorization')).toBe(
      'Bearer 1|token-de-ana',
    );
    expect(
      backend.expectOne('https://otro-servidor.example/algo').request.headers.has('Authorization'),
    ).toBe(false);
  });

  it('ante un 401 cierra la sesión y vuelve al login', async () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const request = firstValueFrom(http.get(`${environment.apiUrl}/wishlists`));

    backend
      .expectOne(`${environment.apiUrl}/wishlists`)
      .flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });

    await expect(request).rejects.toBeTruthy();
    expect(TestBed.inject(AuthService).isAuthenticated()).toBe(false);
    expect(localStorage.getItem(TOKEN_KEY)).toBeNull();
    expect(navigate).toHaveBeenCalledWith('/login');
  });
});
