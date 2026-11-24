import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ProfileChanges, User } from './models';

/** Editar mi perfil y mi foto. Cada respuesta trae el perfil al día. */
@Injectable({ providedIn: 'root' })
export class MeService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/me`;

  async update(changes: ProfileChanges): Promise<User> {
    const response = await firstValueFrom(this.http.patch<{ data: User }>(this.base, changes));
    return response.data;
  }

  async setAvatar(file: File): Promise<User> {
    const form = new FormData();
    form.append('avatar', file);
    const response = await firstValueFrom(
      this.http.put<{ data: User }>(`${this.base}/avatar`, form),
    );
    return response.data;
  }

  async removeAvatar(): Promise<User> {
    const response = await firstValueFrom(this.http.delete<{ data: User }>(`${this.base}/avatar`));
    return response.data;
  }
}
