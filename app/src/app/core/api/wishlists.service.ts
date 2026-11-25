import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page, Wishlist, WishlistInput, WishlistItem } from './models';

@Injectable({ providedIn: 'root' })
export class WishlistsService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/wishlists`;

  /** Mis listas, por fecha del evento. Las de otros se piden por su perfil. */
  mine(page = 1): Promise<Page<Wishlist>> {
    const params = new HttpParams().set('page', page);
    return firstValueFrom(this.http.get<Page<Wishlist>>(this.base, { params }));
  }

  /** Listas públicas de otra gente («Descubrir»), por fecha del evento. */
  discover(page = 1): Promise<Page<Wishlist>> {
    const params = new HttpParams().set('page', page);
    return firstValueFrom(
      this.http.get<Page<Wishlist>>(`${environment.apiUrl}/discover`, { params }),
    );
  }

  /** Una lista, con cuántos regalos tiene pero sin ellos: vienen por página con items(). */
  async show(id: number): Promise<Wishlist> {
    const response = await firstValueFrom(this.http.get<{ data: Wishlist }>(`${this.base}/${id}`));
    return response.data;
  }

  /**
   * Una página de los regalos de una lista, en su orden. Qué campos trae cada regalo depende de
   * si la lista es mía (ver WishlistItem).
   */
  items(id: number, page = 1, perPage = 20): Promise<Page<WishlistItem>> {
    const params = new HttpParams().set('page', page).set('per_page', perPage);
    return firstValueFrom(
      this.http.get<Page<WishlistItem>>(`${this.base}/${id}/items`, { params }),
    );
  }

  /**
   * Abre una lista con su enlace secreto: la API anota el acceso y devuelve la lista sin
   * sus regalos. Después se pide por su id, como cualquier otra.
   */
  async openByLink(token: string): Promise<Wishlist> {
    const response = await firstValueFrom(
      this.http.post<{ data: Wishlist }>(
        `${environment.apiUrl}/shared/${encodeURIComponent(token)}`,
        {},
      ),
    );
    return response.data;
  }

  async create(input: WishlistInput): Promise<Wishlist> {
    const response = await firstValueFrom(this.http.post<{ data: Wishlist }>(this.base, input));
    return response.data;
  }

  async update(id: number, input: WishlistInput): Promise<Wishlist> {
    const response = await firstValueFrom(
      this.http.patch<{ data: Wishlist }>(`${this.base}/${id}`, input),
    );
    return response.data;
  }

  /** Se lleva sus regalos y suelta las reservas que hubiera sobre ellos. */
  async remove(id: number): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.base}/${id}`));
  }
}
