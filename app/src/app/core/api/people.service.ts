import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Page, User, UserProfile } from './models';

@Injectable({ providedIn: 'root' })
export class PeopleService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/users`;

  /** Busca por usuario, con o sin arroba. Con menos de tres letras la API no devuelve a nadie. */
  async search(term: string): Promise<User[]> {
    const params = new HttpParams().set('q', term);
    const response = await firstValueFrom(this.http.get<Page<User>>(this.base, { params }));
    return response.data;
  }

  async show(username: string): Promise<UserProfile> {
    const response = await firstValueFrom(
      this.http.get<{ data: UserProfile }>(`${this.base}/${encodeURIComponent(username)}`),
    );
    return response.data;
  }

  /** Un perfil público queda seguido al instante; uno privado, con la solicitud pendiente. */
  async follow(username: string): Promise<void> {
    await firstValueFrom(this.http.post(`${this.base}/${encodeURIComponent(username)}/follow`, {}));
  }

  /** Dejar de seguir, o cancelar la solicitud. Cierra en el acto sus listas privadas. */
  async unfollow(username: string): Promise<void> {
    await firstValueFrom(this.http.delete(`${this.base}/${encodeURIComponent(username)}/follow`));
  }
}
