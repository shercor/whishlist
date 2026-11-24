import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { FollowLists } from './models';

@Injectable({ providedIn: 'root' })
export class FollowsService {
  private readonly http = inject(HttpClient);

  /** Quién me pide seguirme, quién me sigue, a quién sigo y qué pedí yo. */
  async lists(): Promise<FollowLists> {
    const response = await firstValueFrom(
      this.http.get<{ data: FollowLists }>(`${environment.apiUrl}/follows`),
    );
    return response.data;
  }

  /** Rechazar borra la solicitud: si insiste, será una nueva. */
  async answer(id: number, decision: 'aceptar' | 'rechazar'): Promise<void> {
    await firstValueFrom(this.http.patch(`${environment.apiUrl}/follows/${id}`, { decision }));
  }

  /** Echar a un seguidor. Pierde en el acto el acceso a mis listas privadas. */
  async removeFollower(username: string): Promise<void> {
    await firstValueFrom(
      this.http.delete(`${environment.apiUrl}/users/${encodeURIComponent(username)}/follower`),
    );
  }
}
