import { Injectable, computed, signal } from '@angular/core';
import { PAGE_TRANSITIONS, PageTransition } from './page-transitions';

const KEY = 'whishlist.animacion';

/** La animación al cambiar de pantalla, elegida en el menú de administrador. Se recuerda. */
@Injectable({ providedIn: 'root' })
export class PageTransitionService {
  readonly options = PAGE_TRANSITIONS;

  private readonly chosenId = signal(saved() ?? 'ionic');

  readonly current = computed<PageTransition>(
    () => this.options.find((option) => option.id === this.chosenId()) ?? this.options[0],
  );
  readonly builder = computed(() => this.current().builder);

  choose(id: string): void {
    this.chosenId.set(id);
    try {
      localStorage.setItem(KEY, id);
    } catch {
      // Sin dónde guardarla: vale hasta recargar.
    }
  }
}

function saved(): string | null {
  try {
    const id = localStorage.getItem(KEY);
    return PAGE_TRANSITIONS.some((option) => option.id === id) ? id : null;
  } catch {
    return null;
  }
}
