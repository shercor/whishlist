import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Counts, LoginResponse, User } from '../api/models';

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
  private readonly currentCounts = signal<Counts>({ unread_notifications: 0, pending_requests: 0 });

  readonly token = this.currentToken.asReadonly();
  readonly user = this.currentUser.asReadonly();
  /** Avisos sin leer y solicitudes por responder, para el menú. */
  readonly counts = this.currentCounts.asReadonly();
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
    await this.refresh();
  }

  /** Vuelve a pedir el perfil y los contadores. El menú lo llama al cambiar de pantalla. */
  async refresh(): Promise<void> {
    if (!this.currentToken()) {
      return;
    }

    try {
      const response = await firstValueFrom(
        this.http.get<{ data: User; meta: Counts }>(`${environment.apiUrl}/me`),
      );
      this.currentUser.set(response.data);
      this.currentCounts.set(response.meta);
    } catch {
      // Sin red: se conserva el token y las pantallas muestran su propio error.
    }
  }

  /** Tras editar el perfil o la foto, el menú muestra lo nuevo sin otra petición. */
  setUser(user: User): void {
    this.currentUser.set(user);
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
    this.currentCounts.set({ unread_notifications: 0, pending_requests: 0 });
  }
}
