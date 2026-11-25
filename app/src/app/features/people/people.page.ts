import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonMenuButton,
  IonSkeletonText,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { peopleOutline, searchOutline } from 'ionicons/icons';
import { User } from '../../core/api/models';
import { PeopleService } from '../../core/api/people.service';
import { errorMessage } from '../../core/http/api-error';
import { Avatar } from '../../shared/avatar';
import { UserMenu } from '../../shared/user-menu';

/** Con menos letras la API no devuelve a nadie: el directorio no se puede recorrer entero. */
const MIN_LETTERS = 3;

/** Buscar a alguien por su usuario. Los nombres no se buscan. */
@Component({
  selector: 'app-people',
  imports: [
    UserMenu,
    RouterLink,
    Avatar,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonMenuButton,
    IonSkeletonText,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './people.page.html',
})
export class PeoplePage {
  private readonly api = inject(PeopleService);

  protected readonly query = signal('');
  protected readonly results = signal<User[]>([]);
  protected readonly searching = signal(false);
  /** El término de los resultados que se ven: el estado vacío habla de lo que se buscó, no de lo que se escribe. */
  protected readonly searched = signal('');
  protected readonly error = signal<string | null>(null);
  protected readonly tooShort = computed(() => this.letters(this.query()) < MIN_LETTERS);
  protected readonly skeletonRows = ['35%', '50%', '28%'];

  private searchTimer?: ReturnType<typeof setTimeout>;
  /** Solo cuenta la respuesta de la última búsqueda: una lenta no pisa a una más nueva. */
  private request = 0;

  constructor() {
    addIcons({ peopleOutline, searchOutline });
  }

  protected typed(value: string): void {
    this.query.set(value);
    clearTimeout(this.searchTimer);
    if (this.tooShort()) {
      this.request++;
      this.results.set([]);
      this.searched.set('');
      this.searching.set(false);
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
