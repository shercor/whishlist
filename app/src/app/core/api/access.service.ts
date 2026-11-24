import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AccessStatus, WishlistAccess } from './models';

@Injectable({ providedIn: 'root' })
export class AccessService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  /** Lo que me piden sobre mis listas y lo que pedí yo sobre las de otros. */
  async lists(): Promise<{ received: WishlistAccess[]; sent: WishlistAccess[] }> {
    const response = await firstValueFrom(
      this.http.get<{ data: { received: WishlistAccess[]; sent: WishlistAccess[] } }>(
        `${this.api}/access`,
      ),
    );
    return response.data;
  }

  async request(wishlistId: number, message: string | null): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api}/wishlists/${wishlistId}/access`, { message }));
  }

  async answer(id: number, status: Exclude<AccessStatus, 'pendiente'>): Promise<void> {
    await firstValueFrom(this.http.patch(`${this.api}/access/${id}`, { status }));
  }

  /** Darle una lista privada a un seguidor, sin que la pida. */
  async invite(wishlistId: number, username: string): Promise<void> {
    await firstValueFrom(
      this.http.post(`${this.api}/wishlists/${wishlistId}/invitations`, { username }),
    );
  }

  /** Quitarle la lista a alguien. Se borra, así se le puede volver a dar después. */
  async revoke(wishlistId: number, id: number): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.api}/wishlists/${wishlistId}/access/${id}`));
  }
}
