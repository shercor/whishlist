import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideIonicAngular } from '@ionic/angular';
import { environment } from '../../../environments/environment';
import { Wishlist, WishlistItem } from '../../core/api/models';
import { WishlistPage } from './wishlist.page';

function item(overrides: Partial<WishlistItem>): WishlistItem {
  return {
    id: 1,
    name: 'Dixit',
    notes: null,
    priority: 'media',
    position: 1,
    received: false,
    ...overrides,
  };
}

describe('WishlistPage', () => {
  let backend: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [WishlistPage],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        provideIonicAngular(),
      ],
    }).compileComponents();
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  async function render(wishlist: Wishlist): Promise<string> {
    const fixture = TestBed.createComponent(WishlistPage);
    fixture.componentRef.setInput('id', String(wishlist.id));
    fixture.componentInstance.ionViewWillEnter();
    backend.expectOne(`${environment.apiUrl}/wishlists/${wishlist.id}`).flush({ data: wishlist });
    await fixture.whenStable();
    fixture.detectChanges();
    return (fixture.nativeElement as HTMLElement).textContent ?? '';
  }

  const items = [
    item({ id: 1, name: 'Dixit', is_reserved: true, reserved_by_me: true, my_reservation_id: 9 }),
    item({ id: 2, name: 'Dune', is_reserved: true }),
    item({ id: 3, name: 'Acuarelas', is_reserved: false }),
  ];

  it('a quien regala le muestra qué está tomado, sin marcar lo disponible', async () => {
    const text = await render({
      id: 15,
      name: 'Amigo secreto',
      description: null,
      visibility: 'publica',
      event_date: null,
      is_mine: false,
      owner: {
        username: 'bruno',
        handle: '@bruno',
        display_name: 'Bruno',
        avatar: null,
        initials: 'B',
      },
      items,
    });

    expect(text).toContain('Bruno no ve nada de esta pantalla');
    expect(text).toContain('Lo reservaste tú');
    expect(text).toContain('Ya lo reservaron');
    expect(text).not.toContain('Disponible');
  });

  it('al dueño no le muestra nada de reservas, aunque la respuesta trajera esos campos', async () => {
    const text = await render({
      id: 2,
      name: 'Navidad',
      description: null,
      visibility: 'privada',
      event_date: null,
      is_mine: true,
      share_token: 'abc',
      items,
    });

    expect(text).toContain('Acá no verás si alguien reservó tus regalos');
    expect(text).not.toContain('Lo reservaste tú');
    expect(text).not.toContain('Ya lo reservaron');
  });
});
