import { Injectable, computed, effect, signal } from '@angular/core';

/** Dónde se guarda el tema elegido. index.html lo lee antes de dibujar: si cambia, cambiarlo ahí. */
export const THEME_KEY = 'whishlist.tema';

type Theme = 'claro' | 'oscuro';

/**
 * Modo claro u oscuro. Sin elección guardada sigue al sistema, también si cambia con la app
 * abierta. El botón cambia al contrario del que se ve, como en la web Blade.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly media =
    typeof matchMedia === 'function' ? matchMedia('(prefers-color-scheme: dark)') : null;
  private readonly systemDark = signal(this.media?.matches ?? false);
  private readonly choice = signal<Theme | null>(savedChoice());

  readonly isDark = computed(() => {
    const choice = this.choice();
    return choice ? choice === 'oscuro' : this.systemDark();
  });

  constructor() {
    this.media?.addEventListener('change', (event) => this.systemDark.set(event.matches));
    effect(() => document.documentElement.classList.toggle('ion-palette-dark', this.isDark()));
  }

  toggle(): void {
    const next: Theme = this.isDark() ? 'claro' : 'oscuro';
    this.choice.set(next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch {
      // Sin dónde guardarlo: el cambio vale hasta recargar.
    }
  }
}

function savedChoice(): Theme | null {
  try {
    const saved = localStorage.getItem(THEME_KEY);
    return saved === 'claro' || saved === 'oscuro' ? saved : null;
  } catch {
    return null;
  }
}
