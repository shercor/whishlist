import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AppNotification } from './models';

export interface NotificationPage {
  data: AppNotification[];
  meta: { unread: number; total: number; per_page: number; current_page: number };
}

@Injectable({ providedIn: 'root' })
export class NotificationsService {
  private readonly http = inject(HttpClient);
  private readonly base = `${environment.apiUrl}/notifications`;

  list(page = 1): Promise<NotificationPage> {
    const params = new HttpParams().set('page', page);
    return firstValueFrom(this.http.get<NotificationPage>(this.base, { params }));
  }

  async markRead(id: string): Promise<void> {
    await firstValueFrom(this.http.patch(`${this.base}/${id}`, {}));
  }

  async readAll(): Promise<void> {
    await firstValueFrom(this.http.post(`${this.base}/read-all`, {}));
  }
}
