import { TestBed } from '@angular/core/testing';
import { THEME_KEY, ThemeService } from './theme.service';

describe('ThemeService', () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('ion-palette-dark');
  });

  it('respeta el tema guardado aunque el sistema diga otra cosa', () => {
    localStorage.setItem(THEME_KEY, 'oscuro');
    const theme = TestBed.inject(ThemeService);
    TestBed.tick();

    expect(theme.isDark()).toBe(true);
    expect(document.documentElement.classList.contains('ion-palette-dark')).toBe(true);
  });

  it('el botón cambia al contrario del que se ve y lo recuerda', () => {
    localStorage.setItem(THEME_KEY, 'oscuro');
    const theme = TestBed.inject(ThemeService);

    theme.toggle();
    TestBed.tick();

    expect(theme.isDark()).toBe(false);
    expect(localStorage.getItem(THEME_KEY)).toBe('claro');
    expect(document.documentElement.classList.contains('ion-palette-dark')).toBe(false);
  });
});
