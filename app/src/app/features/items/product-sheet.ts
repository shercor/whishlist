import { Component, computed, inject, input, output, signal } from '@angular/core';
import { IonButton, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, heart, heartOutline, openOutline } from 'ionicons/icons';
import { ItemPriority, Product, WishlistItem } from '../../core/api/models';
import { CatalogService } from '../../core/api/catalog.service';
import { ItemsService } from '../../core/api/items.service';
import { errorMessage } from '../../core/http/api-error';
import { formatPrice } from '../../shared/format';
import { Notify } from '../../shared/notify';
import { PRIORITIES, priorityOf } from '../../shared/priority';

/** Una ficha del catálogo: verla, votarla y agregarla a la lista con su prioridad y notas. */
@Component({
  selector: 'app-product-sheet',
  imports: [IonButton, IonIcon, IonSpinner],
  templateUrl: './product-sheet.html',
})
export class ProductSheet {
  private readonly items = inject(ItemsService);
  private readonly catalog = inject(CatalogService);
  private readonly notify = inject(Notify);

  readonly product = input.required<Product>();
  readonly wishlistId = input.required<number>();
  readonly added = output<WishlistItem>();
  /** Cambió el voto o se retiró del catálogo. */
  readonly productChanged = output<Product>();
  readonly closed = output<void>();

  protected readonly notes = signal('');
  protected readonly priority = signal<ItemPriority>('media');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly priorities = PRIORITIES;
  protected readonly chosenPriority = computed(() => priorityOf(this.priority()));
  protected readonly formatPrice = formatPrice;

  constructor() {
    addIcons({ closeOutline, heart, heartOutline, openOutline });
  }

  protected async add(event: Event): Promise<void> {
    event.preventDefault();
    await this.run(async () => {
      const item = await this.items.create(this.wishlistId(), {
        product_id: this.product().id,
        notes: this.notes().trim() || null,
        priority: this.priority(),
      });
      this.added.emit(item);
    }, 'No se pudo agregar. Intenta de nuevo.');
  }

  protected async toggleLike(): Promise<void> {
    const product = this.product();
    const liked = !product.liked_by_me;
    await this.run(async () => {
      await this.catalog.setLiked(product.id, liked);
      this.productChanged.emit({
        ...product,
        liked_by_me: liked,
        likes: (product.likes ?? 0) + (liked ? 1 : -1),
      });
    }, 'No se pudo guardar tu voto.');
  }

  protected async unpublish(): Promise<void> {
    const product = this.product();
    const confirmed = await this.notify.confirm(
      `¿Retirar «${product.name}» del catálogo?`,
      'Las listas que ya la tienen no cambian, pero se pierden sus votos.',
      'Retirar',
    );
    if (!confirmed) {
      return;
    }

    await this.run(async () => {
      await this.catalog.unpublish(product.id);
      this.productChanged.emit({ ...product, is_public: false, likes: 0, liked_by_me: false });
      await this.notify.toast('Ficha retirada del catálogo.');
    }, 'No se pudo retirar la ficha.');
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  private async run(action: () => Promise<void>, fallback: string): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    try {
      await action();
    } catch (error) {
      this.error.set(errorMessage(error, fallback));
    } finally {
      this.busy.set(false);
    }
  }
}
