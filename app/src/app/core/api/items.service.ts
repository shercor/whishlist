import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ItemChanges, NewItem, WishlistItem } from './models';

@Injectable({ providedIn: 'root' })
export class ItemsService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/items`;

  /** Con foto viaja como multipart; sin ella, como json. */
  async create(wishlistId: number, item: NewItem): Promise<WishlistItem> {
    const url = `${environment.apiUrl}/wishlists/${wishlistId}/items`;
    const body = item.image ? toFormData(item) : withoutEmpty(item);
    const response = await firstValueFrom(this.http.post<{ data: WishlistItem }>(url, body));
    return response.data;
  }

  async update(id: number, changes: ItemChanges): Promise<WishlistItem> {
    const response = await firstValueFrom(
      this.http.patch<{ data: WishlistItem }>(`${this.base}/${id}`, changes),
    );
    return response.data;
  }

  async remove(id: number): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.base}/${id}`));
  }

  /** «Ya me llegó»: cierra la reserva que hubiera, como cumplida. */
  async setReceived(id: number, received: boolean): Promise<WishlistItem> {
    const url = `${this.base}/${id}/receipt`;
    const request = received
      ? this.http.put<{ data: WishlistItem }>(url, {})
      : this.http.delete<{ data: WishlistItem }>(url);
    const response = await firstValueFrom(request);
    return response.data;
  }
}

/** Los campos vacíos no se mandan: la API los trata como «no lo dijo». */
function withoutEmpty(item: NewItem): Partial<NewItem> {
  return Object.fromEntries(
    Object.entries(item).filter(
      ([, value]) => value !== null && value !== undefined && value !== '',
    ),
  );
}

function toFormData(item: NewItem): FormData {
  const form = new FormData();
  for (const [key, value] of Object.entries(withoutEmpty(item))) {
    if (value instanceof File) {
      form.append(key, value);
    } else if (typeof value === 'boolean') {
      // La regla boolean de Laravel acepta 1 y 0, pero no "true".
      form.append(key, value ? '1' : '0');
    } else {
      form.append(key, String(value));
    }
  }
  return form;
}
