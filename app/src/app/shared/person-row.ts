import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { User } from '../core/api/models';
import { Avatar } from './avatar';

/**
 * Fila de una persona en una tarjeta: su foto y nombre llevan a su perfil, y lo que se proyecta
 * adentro (aceptar, quitar...) va a la derecha. En el teléfono las acciones bajan si no caben.
 */
@Component({
  selector: 'app-person-row',
  imports: [RouterLink, Avatar],
  template: `
    <a
      class="flex min-w-0 flex-1 basis-48 items-center gap-3 text-inherit no-underline"
      [routerLink]="['/u', user().username]"
    >
      <app-avatar [user]="user()" class="size-10 text-sm md:size-9" />
      <div class="min-w-0">
        <div class="truncate font-medium">{{ title() ?? user().display_name }}</div>
        @if (secondLine(); as text) {
          <div class="truncate text-sm text-neutral-500 dark:text-neutral-400">{{ text }}</div>
        }
      </div>
    </a>
    <div class="ms-auto flex shrink-0 items-center gap-3">
      <ng-content />
    </div>
  `,
  host: {
    role: 'listitem',
    class:
      'flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-neutral-100 px-4 py-3 last:border-b-0 md:px-5 dark:border-white/5',
  },
})
export class PersonRow {
  readonly user = input.required<User>();
  /** Por defecto, su nombre público. */
  readonly title = input<string | null>(null);
  /** Por defecto, su usuario, si no es lo mismo que ya dice el título. */
  readonly subtitle = input<string | null>(null);

  protected readonly secondLine = computed(() => {
    const user = this.user();
    if (this.subtitle()) {
      return this.subtitle();
    }
    return (this.title() ?? user.display_name) === user.handle ? null : user.handle;
  });
}
