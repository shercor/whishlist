import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { environment } from '../../../environments/environment';
import { ItemsService } from './items.service';

describe('ItemsService.create', () => {
  let service: ItemsService;
  let backend: HttpTestingController;
  const url = `${environment.apiUrl}/wishlists/7/items`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(ItemsService);
    backend = TestBed.inject(HttpTestingController);
  });

  afterEach(() => backend.verify());

  it('sin foto manda json y deja fuera los campos vacíos', async () => {
    const done = service.create(7, {
      name: 'Taza',
      category_id: 3,
      description: '',
      url: null,
      reference_price: 8990,
      share_with_catalog: false,
      notes: '',
      priority: 'alta',
    });

    const request = backend.expectOne(url);
    expect(request.request.body).toEqual({
      name: 'Taza',
      category_id: 3,
      reference_price: 8990,
      share_with_catalog: false,
      priority: 'alta',
    });
    request.flush({ data: { id: 1 } });
    await done;
  });

  it('con foto manda multipart, con los sí/no como 1 y 0', async () => {
    const image = new File(['x'], 'taza.png', { type: 'image/png' });
    const done = service.create(7, {
      name: 'Taza',
      category_id: 3,
      description: '',
      image,
      share_with_catalog: true,
      priority: 'media',
    });

    const request = backend.expectOne(url);
    const body = request.request.body as FormData;
    expect(body).toBeInstanceOf(FormData);
    expect(body.get('image')).toBeInstanceOf(File);
    expect(body.get('share_with_catalog')).toBe('1');
    expect(body.get('category_id')).toBe('3');
    expect(body.has('description')).toBe(false);
    request.flush({ data: { id: 1 } });
    await done;
  });
});
