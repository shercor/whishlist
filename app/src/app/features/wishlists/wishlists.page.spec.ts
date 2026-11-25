import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideIonicAngular } from '@ionic/angular';
import { environment } from '../../../environments/environment';
import { Wishlist } from '../../core/api/models';
import { WishlistsPage } from './wishlists.page';

function wishlist(overrides: Partial<Wishlist>): Wishlist {
  return {
    id: 1,
    name: 'Cumpleaños',
    description: null,
    visibility: 'privada',
    event_date: null,
    items_count: 0,
    is_mine: true,
    ...overrides,
  };
}

describe('WishlistsPage', () => {
  let backend: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [WishlistsPage],
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

  async function render(data: Wishlist[]): Promise<HTMLElement> {
    const fixture = TestBed.createComponent(WishlistsPage);
    fixture.componentInstance.ionViewWillEnter();
    backend
      .expectOne((request) => request.url === `${environment.apiUrl}/wishlists`)
      .flush({ data, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length } });
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture.nativeElement;
  }

  it('muestra cada lista con sus regalos, su fecha y quién puede verla', async () => {
    const page = await render([
      wishlist({
        id: 1,
        name: 'Navidad',
        items_count: 3,
        event_date: '2026-12-24',
        visibility: 'publica',
      }),
      wishlist({ id: 2, name: 'Cumpleaños', items_count: 0 }),
    ]);

    const rows = [...page.querySelectorAll('[role="listitem"]')].map((row) =>
      row.textContent?.replace(/\s+/g, ' ').trim(),
    );
    // La fecha va dos veces, pegadas: corta para el teléfono y larga desde md. El CSS muestra una.
    // Sin inicial delante: cada fila lleva el regalo de la marca, que es una imagen sin texto.
    expect(rows).toEqual([
      'Navidad Pública 3 regalos · 24 dic 202624 de diciembre de 2026',
      'Cumpleaños Privada Vacía todavía',
    ]);
  });

  it('sin listas invita a crear la primera', async () => {
    const page = await render([]);

    expect(page.textContent).toContain('Todavía no tienes listas');
    expect(page.querySelector('ion-button[routerLink="/listas/nueva"]')).not.toBeNull();
  });
});
