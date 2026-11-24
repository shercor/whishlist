import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideIonicAngular } from '@ionic/angular';
import { App } from './app';

describe('App', () => {
  // jsdom no trae matchMedia, y el menú lateral de Ionic lo usa para decidir si queda fijo.
  beforeEach(() => {
    vi.stubGlobal('matchMedia', (media: string) => ({
      matches: false,
      media,
      onchange: null,
      addListener: () => undefined,
      removeListener: () => undefined,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      dispatchEvent: () => false,
    }));
  });

  afterEach(() => vi.unstubAllGlobals());

  it('monta la app de Ionic con su router', async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideHttpClient(), provideRouter([]), provideIonicAngular()],
    }).compileComponents();

    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('ion-app ion-router-outlet')).not.toBeNull();
  });
});
