import { Component, input } from '@angular/core';
import { User } from '../core/api/models';

/** Foto de perfil en círculo, o las iniciales si no subió una. */
@Component({
  selector: 'app-avatar',
  template: `
    @if (user().avatar; as src) {
      <img
        [src]="src"
        alt=""
        class="block size-full rounded-full object-cover"
        loading="lazy"
        aria-hidden="true"
      />
    } @else {
      <span
        aria-hidden="true"
        class="flex size-full items-center justify-center rounded-full bg-neutral-200 text-[0.8em] font-semibold text-neutral-600 dark:bg-white/10 dark:text-neutral-300"
      >
        {{ user().initials }}
      </span>
    }
  `,
  host: { class: 'inline-block shrink-0' },
})
export class Avatar {
  readonly user = input.required<Pick<User, 'avatar' | 'initials'>>();
}
