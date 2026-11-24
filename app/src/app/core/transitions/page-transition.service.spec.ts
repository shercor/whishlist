import { TestBed } from '@angular/core/testing';
import { PAGE_TRANSITIONS } from './page-transitions';
import { PageTransitionService } from './page-transition.service';

describe('PageTransitionService', () => {
  beforeEach(() => localStorage.clear());

  it('ofrece la de ahora, ocho animaciones y sin animación, sin ids repetidos', () => {
    const ids = PAGE_TRANSITIONS.map((option) => option.id);
    expect(ids.length).toBe(10);
    expect(new Set(ids).size).toBe(10);
  });

  it('sin elegir usa la de Ionic, y lo elegido se recuerda al volver a abrir', () => {
    const first = TestBed.inject(PageTransitionService);
    expect(first.current().id).toBe('ionic');

    first.choose('barrido');
    TestBed.resetTestingModule();

    const again = TestBed.inject(PageTransitionService);
    expect(again.current().id).toBe('barrido');
    expect(again.builder()).toBe(
      PAGE_TRANSITIONS.find((option) => option.id === 'barrido')?.builder,
    );
  });

  it('un id guardado que ya no existe vuelve a la de Ionic', () => {
    localStorage.setItem('whishlist.animacion', 'una-que-se-borro');
    expect(TestBed.inject(PageTransitionService).current().id).toBe('ionic');
  });
});
