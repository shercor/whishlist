import { Component, computed, inject, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonIcon, IonSkeletonText } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { searchOutline } from 'ionicons/icons';
import { User } from '../../core/api/models';
import { PeopleService } from '../../core/api/people.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';

/** Con menos letras la API no devuelve a nadie: el directorio no se puede recorrer entero. */
const MIN_LETTERS = 3;

/**
 * Buscar a alguien por su usuario (los nombres no se buscan), dentro de la pantalla de Gente: el
 * campo va arriba de tu gente y, mientras hay algo que buscar, los resultados toman su lugar.
 * Avisa con `active` para que la pantalla esconda sus listas y no queden dos cosas a la vez.
 */
@Component({
  selector: 'app-people-search',
  imports: [RouterLink, Avatar, IonIcon, IonSkeletonText],
  template: `
    <!-- El margen va en un contenedor: .control lo pone en 0 y le gana a mb-4. -->
    <div class="mb-4">
      <input
        type="search"
        class="control w-full"
        placeholder="Buscar por usuario: @ana_perez"
        aria-label="Buscar personas por usuario"
        autocapitalize="none"
        autocomplete="off"
        spellcheck="false"
        enterkeyhint="search"
        [value]="query()"
        (input)="typed(valueOf($event))"
      />
      @if (query().trim() && tooShort()) {
        <p class="hint mt-1.5 mb-0">Escribe al menos tres letras de su usuario.</p>
      }
    </div>

    @if (error(); as message) {
      <div
        role="alert"
        animate.enter="enter-down"
        class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300"
      >
        {{ message }}
      </div>
    }

    @if (!tooShort()) {
      <h2 class="section-title">Resultados</h2>
      @if (searching() && results().length === 0) {
        <div role="status" aria-label="Buscando" class="card mb-6 overflow-hidden">
          @for (width of skeletonRows; track $index) {
            <div
              aria-hidden="true"
              class="flex items-center gap-3 border-b border-neutral-100 px-4 py-3.5 last:border-b-0 md:px-5 dark:border-white/5"
            >
              <ion-skeleton-text animated class="skeleton-round m-0 size-10 shrink-0 md:size-9" />
              <div class="flex min-w-0 flex-1 flex-col gap-2">
                <ion-skeleton-text animated class="m-0 h-4" [style.width]="width" />
                <ion-skeleton-text animated class="m-0 h-3 w-1/4" />
              </div>
            </div>
          }
        </div>
      } @else if (results().length > 0) {
        <div
          role="list"
          aria-label="Personas encontradas"
          class="card mb-6 overflow-hidden transition-opacity"
          [class.opacity-60]="searching()"
          [attr.aria-busy]="searching()"
        >
          @for (person of results(); track person.username) {
            <a
              role="listitem"
              animate.enter="enter-up"
              class="tappable-row grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-0.5 border-b border-neutral-100 px-4 py-3.5 text-inherit no-underline transition-colors last:border-b-0 md:gap-x-4 md:px-5 md:hover:bg-neutral-50 dark:border-white/5 md:dark:hover:bg-white/5"
              [routerLink]="['/u', person.username]"
            >
              <app-avatar [user]="person" class="row-span-2 size-10 text-sm md:size-9" />
              <div class="truncate font-medium">{{ person.handle }}</div>
              <div class="col-start-2 truncate text-sm text-neutral-500 dark:text-neutral-400">
                {{ person.display_name !== person.handle ? person.display_name : 'Ver sus listas' }}
              </div>
            </a>
          }
        </div>
      } @else if (searched() && !error()) {
        <div class="card enter-up mb-6 flex flex-col items-center gap-2 px-6 py-10 text-center">
          <div
            class="icon-float flex size-12 items-center justify-center rounded-full bg-amber-50 text-2xl text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
          >
            <ion-icon name="search-outline" aria-hidden="true" />
          </div>
          <h2 class="mt-2 mb-0 text-lg font-semibold">Nadie usa «{{ searched() }}»</h2>
          <p class="m-0 max-w-sm text-sm text-neutral-500 dark:text-neutral-400">
            Revisa cómo se escribe: se busca por el usuario, no por el nombre.
          </p>
        </div>
      }
    }
  `,
})
export class PeopleSearch {
  private readonly api = inject(PeopleService);

  /** Si hay una búsqueda en curso (tres letras o más): la pantalla esconde lo demás. */
  readonly active = output<boolean>();

  protected readonly query = signal('');
  protected readonly results = signal<User[]>([]);
  protected readonly searching = signal(false);
  /** El término de los resultados que se ven: el «nadie» habla de lo que se buscó, no de lo que se escribe. */
  protected readonly searched = signal('');
  protected readonly error = signal<string | null>(null);
  protected readonly tooShort = computed(() => this.letters(this.query()) < MIN_LETTERS);
  protected readonly skeletonRows = ['35%', '50%', '28%'];

  private searchTimer?: ReturnType<typeof setTimeout>;
  /** Solo cuenta la respuesta de la última búsqueda: una lenta no pisa a una más nueva. */
  private request = 0;

  constructor() {
    addIcons({ searchOutline });
  }

  protected typed(value: string): void {
    this.query.set(value);
    this.active.emit(!this.tooShort());
    clearTimeout(this.searchTimer);
    if (this.tooShort()) {
      this.request++;
      this.results.set([]);
      this.searched.set('');
      this.searching.set(false);
      this.error.set(null);
      return;
    }
    this.searching.set(true);
    this.searchTimer = setTimeout(() => void this.search(), 300);
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  private async search(): Promise<void> {
    const term = this.query().trim();
    const request = ++this.request;
    this.error.set(null);
    try {
      const people = await this.api.search(term);
      if (request === this.request) {
        this.results.set(people);
        this.searched.set(term);
      }
    } catch (error) {
      if (request === this.request) {
        this.error.set(errorMessage(error, 'No se pudo buscar. Intenta de nuevo.'));
      }
    } finally {
      if (request === this.request) {
        this.searching.set(false);
      }
    }
  }

  private letters(term: string): number {
    return term.trim().replace(/^@/, '').length;
  }
}
