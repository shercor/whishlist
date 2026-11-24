import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Category, Page, Product } from './models';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);
  private readonly products = `${environment.apiUrl}/products`;

  async categories(): Promise<Category[]> {
    const response = await firstValueFrom(
      this.http.get<{ data: Category[] }>(`${environment.apiUrl}/categories`),
    );
    return response.data;
  }

  /** Por el comienzo de cada palabra: «pelu» encuentra «Peluche». Sin texto, lo más votado. */
  search(query: string): Promise<Page<Product>> {
    const params = query.trim() ? new HttpParams().set('q', query.trim()) : undefined;
    return firstValueFrom(this.http.get<Page<Product>>(this.products, { params }));
  }

  async setLiked(id: number, liked: boolean): Promise<void> {
    const url = `${this.products}/${id}/like`;
    await firstValueFrom(liked ? this.http.put(url, {}) : this.http.delete(url));
  }

  /** Retira del catálogo una ficha propia. Las listas que ya la tienen no cambian. */
  async unpublish(id: number): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.products}/${id}/publication`));
  }
}
