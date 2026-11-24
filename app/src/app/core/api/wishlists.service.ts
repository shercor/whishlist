import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page, Wishlist, WishlistInput } from './models';

@Injectable({ providedIn: 'root' })
export class WishlistsService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/wishlists`;

  /** Mis listas, por fecha del evento. Las de otros se piden por su perfil. */
  mine(page = 1): Promise<Page<Wishlist>> {
    const params = new HttpParams().set('page', page);
    return firstValueFrom(this.http.get<Page<Wishlist>>(this.base, { params }));
  }

  /** Una lista con sus regalos. Qué campos trae cada regalo depende de si es mía (ver WishlistItem). */
  async show(id: number): Promise<Wishlist> {
    const response = await firstValueFrom(this.http.get<{ data: Wishlist }>(`${this.base}/${id}`));
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
