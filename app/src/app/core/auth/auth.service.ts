import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { LoginResponse, User } from '../api/models';

export const TOKEN_KEY = 'whishlist.token';

/** Identifica el token en la lista de dispositivos de la API. */
const DEVICE_NAME = 'Navegador web';

/**
 * Sesión contra la API: un token de Sanctum guardado en el dispositivo.
 *
 * En web vive en localStorage. Al empaquetar con Capacitor conviene moverlo al
 * almacenamiento seguro del teléfono.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly currentToken = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  private readonly currentUser = signal<User | null>(null);

  readonly token = this.currentToken.asReadonly();
  readonly user = this.currentUser.asReadonly();
  readonly isAuthenticated = computed(() => this.currentToken() !== null);

  async login(email: string, password: string): Promise<void> {
    const response = await firstValueFrom(
      this.http.post<LoginResponse>(`${environment.apiUrl}/tokens`, {
        email,
        password,
        device_name: DEVICE_NAME,
      }),
    );

    localStorage.setItem(TOKEN_KEY, response.token);
    this.currentToken.set(response.token);
    this.currentUser.set(response.user);
  }

  /**
   * Al arrancar, si hay un token guardado pide el perfil. Si el token ya no
   * sirve, la API responde 401 y el interceptor cierra la sesión.
   */
  async restore(): Promise<void> {
    if (!this.currentToken()) {
      return;
    }

    try {
      const response = await firstValueFrom(
        this.http.get<{ data: User }>(`${environment.apiUrl}/me`),
      );
      this.currentUser.set(response.data);
    } catch {
      // Sin red: se conserva el token y las pantallas muestran su propio error.
    }
  }

  /**
   * Revoca el token en la API. La sesión local se borra aunque la API no responda.
   */
  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.http.delete(`${environment.apiUrl}/tokens/current`));
    } finally {
      this.clear();
    }
  }

  clear(): void {
    localStorage.removeItem(TOKEN_KEY);
    this.currentToken.set(null);
    this.currentUser.set(null);
  }
}
