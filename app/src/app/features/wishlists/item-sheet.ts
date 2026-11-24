import { Component, computed, inject, input, linkedSignal, output, signal } from '@angular/core';
import { IonButton, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { checkmarkCircleOutline, closeOutline, openOutline } from 'ionicons/icons';
import { ItemPriority, WishlistItem } from '../../core/api/models';
import { ItemsService } from '../../core/api/items.service';
import { errorMessage } from '../../core/http/api-error';
import { formatPrice } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { PRIORITIES, priorityOf } from '../../shared/priority';

/**
 * Un regalo de mi propia lista: su ficha, editar cómo lo llamo, las notas y la prioridad,
 * marcarlo como recibido o quitarlo. Nada aquí habla de reservas: es lo que protege la sorpresa.
 */
@Component({
  selector: 'app-item-sheet',
  imports: [IonButton, IonIcon, IonSpinner],
  templateUrl: './item-sheet.html',
})
export class ItemSheet {
  private readonly api = inject(ItemsService);
  private readonly notify = inject(Notify);

  readonly item = input.required<WishlistItem>();
  readonly changed = output<WishlistItem>();
  readonly removed = output<number>();
  readonly closed = output<void>();

  /** Vacío si usa el nombre del producto, igual que en la web. */
  protected readonly alias = linkedSignal(() => {
    const item = this.item();
    return item.name !== item.product?.name ? item.name : '';
  });
  protected readonly notes = linkedSignal(() => this.item().notes ?? '');
  protected readonly priority = linkedSignal<ItemPriority>(() => this.item().priority);

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly priorities = PRIORITIES;
  protected readonly chosenPriority = computed(() => priorityOf(this.priority()));
  protected readonly formatPrice = formatPrice;

  constructor() {
    addIcons({ checkmarkCircleOutline, closeOutline, openOutline });
  }

  protected async save(event: Event): Promise<void> {
    event.preventDefault();
    await this.run(async () => {
      const item = await this.api.update(this.item().id, {
        alias: this.alias().trim() || null,
        notes: this.notes().trim() || null,
        priority: this.priority(),
      });
      this.changed.emit(item);
      await this.notify.toast('Regalo guardado.');
    });
  }

  protected async toggleReceived(): Promise<void> {
    await this.run(async () => {
      const item = await this.api.setReceived(this.item().id, !this.item().received);
      this.changed.emit(item);
    });
  }

  protected async remove(): Promise<void> {
    const confirmed = await this.notify.confirm(
      `¿Quitar «${this.item().name}» de la lista?`,
      '',
      'Quitar',
    );
    if (!confirmed) {
      return;
    }

    await this.run(async () => {
      await this.api.remove(this.item().id);
      this.removed.emit(this.item().id);
      await this.notify.toast('Regalo quitado de la lista.');
    });
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  private async run(action: () => Promise<void>): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    try {
      await action();
    } catch (error) {
      this.error.set(errorMessage(error, 'No se pudo guardar. Intenta de nuevo.'));
    } finally {
      this.busy.set(false);
    }
  }
}
