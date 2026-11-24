import { Component, computed, inject, input, signal } from '@angular/core';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonLabel,
  IonModal,
  IonSegment,
  IonSegmentButton,
  IonSkeletonText,
  IonSpinner,
  IonTitle,
  IonToolbar,
  NavController,
  ViewWillEnter,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { heart, imageOutline, searchOutline } from 'ionicons/icons';
import { CatalogService } from '../../core/api/catalog.service';
import { ItemsService } from '../../core/api/items.service';
import { Category, ItemPriority, Product } from '../../core/api/models';
import { WishlistsService } from '../../core/api/wishlists.service';
import { errorMessage } from '../../core/http/api-error';
import { Notify } from '../../shared/notify';
import { PRIORITIES, priorityOf } from '../../shared/priority';
import { ProductSheet } from './product-sheet';

type Mode = 'catalogo' | 'manual';

/** Agregar un regalo a una lista propia: elegido del catálogo o escrito a mano. */
@Component({
  selector: 'app-item-form',
  imports: [
    ProductSheet,
    IonBackButton,
    IonButton,
    IonButtons,
    IonContent,
    IonHeader,
    IonIcon,
    IonLabel,
    IonModal,
    IonSegment,
    IonSegmentButton,
    IonSkeletonText,
    IonSpinner,
    IonTitle,
    IonToolbar,
  ],
  templateUrl: './item-form.page.html',
})
export class ItemFormPage implements ViewWillEnter {
  private readonly catalog = inject(CatalogService);
  private readonly items = inject(ItemsService);
  private readonly wishlists = inject(WishlistsService);
  private readonly nav = inject(NavController);
  private readonly notify = inject(Notify);

  /** El :id de la lista, de la ruta. */
  readonly id = input.required<string>();
  protected readonly wishlistId = computed(() => Number(this.id()));
  protected readonly wishlistName = signal('');
  protected readonly mode = signal<Mode>('catalogo');

  // --- Catálogo ---------------------------------------------------------------
  protected readonly query = signal('');
  protected readonly results = signal<Product[]>([]);
  protected readonly searching = signal(true);
  protected readonly searchError = signal<string | null>(null);
  protected readonly selected = signal<Product | null>(null);
  protected readonly modalOpen = signal(false);
  /** En el teléfono el modal es una hoja que sube desde abajo. 48rem = md de Tailwind. */
  protected readonly sheet =
    typeof matchMedia === 'function' && matchMedia('(max-width: 47.99rem)').matches;
  private searchTimer?: ReturnType<typeof setTimeout>;
  /** Cada búsqueda lleva un número: si llega tarde la respuesta de una anterior, se descarta. */
  private request = 0;

  // --- Escrito a mano ---------------------------------------------------------
  protected readonly categories = signal<Category[]>([]);
  protected readonly name = signal('');
  protected readonly categoryId = signal<number | null>(null);
  protected readonly description = signal('');
  protected readonly url = signal('');
  protected readonly price = signal('');
  protected readonly image = signal<File | null>(null);
  protected readonly shareWithCatalog = signal(false);
  protected readonly notes = signal('');
  protected readonly priority = signal<ItemPriority>('media');
  protected readonly saving = signal(false);
  protected readonly saveError = signal<string | null>(null);
  protected readonly canSave = computed(
    () => this.name().trim() !== '' && this.categoryId() !== null && !this.saving(),
  );

  protected readonly priorities = PRIORITIES;
  protected readonly chosenPriority = computed(() => priorityOf(this.priority()));
  protected readonly skeletonRows = ['55%', '40%', '62%', '48%'];

  constructor() {
    addIcons({ heart, imageOutline, searchOutline });
  }

  ionViewWillEnter(): void {
    this.wishlists
      .show(this.wishlistId())
      .then((wishlist) => this.wishlistName.set(wishlist.name))
      .catch(() => undefined);
    this.catalog
      .categories()
      .then((categories) => this.categories.set(categories))
      .catch(() => undefined);
    void this.search();
  }

  protected setMode(value: unknown): void {
    this.mode.set(value === 'manual' ? 'manual' : 'catalogo');
  }

  /** Busca al dejar de escribir, no con cada letra. */
  protected typed(value: string): void {
    this.query.set(value);
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => void this.search(), 300);
  }

  protected choose(product: Product): void {
    this.selected.set(product);
    this.modalOpen.set(true);
  }

  protected onProductChanged(product: Product): void {
    this.selected.set(product);
    this.results.update((list) => list.map((item) => (item.id === product.id ? product : item)));
  }

  protected async onAdded(): Promise<void> {
    this.modalOpen.set(false);
    await this.done();
  }

  protected pickCategory(event: Event): void {
    const value = this.valueOf(event);
    this.categoryId.set(value ? Number(value) : null);
  }

  protected pickImage(event: Event): void {
    this.image.set((event.target as HTMLInputElement).files?.[0] ?? null);
  }

  protected async save(event: Event): Promise<void> {
    event.preventDefault();
    if (!this.canSave()) {
      return;
    }

    this.saving.set(true);
    this.saveError.set(null);
    try {
      await this.items.create(this.wishlistId(), {
        name: this.name().trim(),
        category_id: this.categoryId() ?? undefined,
        description: this.description().trim() || null,
        url: this.url().trim() || null,
        reference_price: this.price() ? Number(this.price()) : null,
        image: this.image(),
        share_with_catalog: this.shareWithCatalog(),
        notes: this.notes().trim() || null,
        priority: this.priority(),
      });
      await this.done();
    } catch (error) {
      this.saveError.set(errorMessage(error, 'No se pudo agregar el regalo. Intenta de nuevo.'));
    } finally {
      this.saving.set(false);
    }
  }

  protected valueOf(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  protected checkedOf(event: Event): boolean {
    return (event.target as HTMLInputElement).checked;
  }

  private async search(): Promise<void> {
    const request = ++this.request;
    this.searching.set(true);
    this.searchError.set(null);
    try {
      const page = await this.catalog.search(this.query());
      if (request === this.request) {
        this.results.set(page.data);
      }
    } catch (error) {
      if (request === this.request) {
        this.searchError.set(errorMessage(error, 'No se pudo buscar en el catálogo.'));
      }
    } finally {
      if (request === this.request) {
        this.searching.set(false);
      }
    }
  }

  private async done(): Promise<void> {
    await this.notify.toast('Regalo agregado.');
    await this.nav.navigateBack(['/listas', this.id()]);
  }
}
