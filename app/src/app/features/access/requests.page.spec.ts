import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideIonicAngular } from '@ionic/angular';
import { environment } from '../../../environments/environment';
import { User, WishlistAccess } from '../../core/api/models';
import { RequestsPage } from './requests.page';

function person(username: string): User {
  return {
    username,
    handle: `@${username}`,
    display_name: `@${username}`,
    avatar: null,
    initials: username.slice(0, 1).toUpperCase(),
  };
}

function access(id: number, username: string, source: WishlistAccess['source']): WishlistAccess {
  return {
    id,
    status: 'aprobado',
    source,
    message: null,
    responded_at: null,
    user: person(username),
    wishlist: { id: 3, name: 'Navidad', owner: person('ana') },
  };
}

describe('RequestsPage', () => {
  it('en «Te pidieron una lista» deja fuera a quien invitaste o entró con el enlace', async () => {
    await TestBed.configureTestingModule({
      imports: [RequestsPage],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        provideIonicAngular(),
      ],
    }).compileComponents();
    const backend = TestBed.inject(HttpTestingController);

    const fixture = TestBed.createComponent(RequestsPage);
    fixture.componentInstance.ionViewWillEnter();
    backend.expectOne(`${environment.apiUrl}/follows`).flush({
      data: { pending_for_me: [], followers: [], following: [], my_pending: [] },
    });
    backend.expectOne(`${environment.apiUrl}/access`).flush({
      data: {
        received: [
          access(1, 'camila', 'solicitud'),
          access(2, 'bruno', 'invitacion'),
          access(3, 'sofi', 'enlace'),
        ],
        sent: [],
      },
    });
    await fixture.whenStable();
    fixture.detectChanges();
    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('@camila');
    expect(text).not.toContain('@bruno');
    expect(text).not.toContain('@sofi');
    backend.verify();
  });
});
