import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideIonicAngular } from '@ionic/angular';
import { environment } from '../../../environments/environment';
import { Wishlist, WishlistItem } from '../../core/api/models';
import { WishlistPage, pageNumbers } from './wishlist.page';

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

  /** La lista y su primera página de regalos llegan por dos pedidos. */
  async function render(wishlist: Wishlist, gifts: WishlistItem[]): Promise<string> {
    const fixture = TestBed.createComponent(WishlistPage);
    fixture.componentRef.setInput('id', String(wishlist.id));
    fixture.componentInstance.ionViewWillEnter();
    backend.expectOne(`${environment.apiUrl}/wishlists/${wishlist.id}`).flush({ data: wishlist });
    backend
      .expectOne((request) => request.url === `${environment.apiUrl}/wishlists/${wishlist.id}/items`)
      .flush({
        data: gifts,
        meta: { current_page: 1, last_page: 1, per_page: 10, total: gifts.length },
      });
    await fixture.whenStable();
    fixture.detectChanges();
    return (fixture.nativeElement as HTMLElement).textContent ?? '';
  }

  const items = [
    item({ id: 1, name: 'Dixit', is_reserved: true, reserved_by_me: true, my_reservation_id: 9 }),
    item({ id: 2, name: 'Dune', is_reserved: true }),
    item({ id: 3, name: 'Acuarelas', is_reserved: false }),
  ];

  it('a quien regala le muestra qué está tomado, y en lo disponible el botón para reservarlo', async () => {
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
    }, items);

    expect(text).toContain('Bruno no ve qué está reservado ni por quién');
    expect(text).toContain('Lo reservaste tú');
    expect(text).toContain('Ya lo reservaron');
    expect(text).not.toContain('Disponible');
    // Solo Acuarelas está libre: un único «Lo regalo yo».
    expect(text.split('Lo regalo yo').length - 1).toBe(1);
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
    }, items);

    expect(text).toContain('No verás si alguien reservó tus regalos');
    expect(text).not.toContain('Lo reservaste tú');
    expect(text).not.toContain('Lo regalo yo');
    expect(text).not.toContain('Ya lo reservaron');
  });

  it('numera las páginas con «…» cuando son muchas', () => {
    expect(pageNumbers(1, 3)).toEqual([1, 2, 3]);
    expect(pageNumbers(1, 12)).toEqual([1, 2, null, 12]);
    expect(pageNumbers(5, 12)).toEqual([1, null, 4, 5, 6, null, 12]);
    expect(pageNumbers(12, 12)).toEqual([1, null, 11, 12]);
  });
});
