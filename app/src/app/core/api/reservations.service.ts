import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page, Reservation } from './models';

@Injectable({ providedIn: 'root' })
export class ReservationsService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/reservations`;

  /** Lo que tengo reservado, primero lo que se suelta antes. */
  mine(page = 1): Promise<Page<Reservation>> {
    const params = new HttpParams().set('page', page);
    return firstValueFrom(this.http.get<Page<Reservation>>(this.base, { params }));
  }

  /** Falla con 409 si alguien lo reservó antes. */
  async reserve(itemId: number): Promise<Reservation> {
    const response = await firstValueFrom(
      this.http.post<{ data: Reservation }>(this.base, { wishlist_item_id: itemId }),
    );
    return response.data;
  }

  async release(id: number): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.base}/${id}`));
  }
}
