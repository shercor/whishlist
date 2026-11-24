/**
 * Formas de las respuestas de la API. El contrato completo está en docs/openapi.yaml.
 */

/** Una persona. El correo y las preferencias solo vienen en el perfil propio. */
export interface User {
  username: string;
  handle: string;
  display_name: string;
  avatar: string | null;
  initials: string;
  email?: string;
  show_name?: boolean;
  is_private?: boolean;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export type WishlistVisibility = 'publica' | 'privada';

export interface Wishlist {
  id: number;
  name: string;
  description: string | null;
  visibility: WishlistVisibility;
  /** aaaa-mm-dd */
  event_date: string | null;
  items_count?: number;
  owner?: User;
  is_mine: boolean;
  /** Solo para el dueño de una lista privada. */
  share_token?: string | null;
  /** Solo en el detalle de una lista. */
  items?: WishlistItem[];
}

export interface Category {
  id: number;
  name: string;
  slug: string;
}

export interface Product {
  id: number;
  name: string;
  description: string | null;
  url: string | null;
  image: string | null;
  /** Decimal como texto: «12990.00». */
  reference_price: string | null;
  currency: string | null;
  is_public: boolean;
  /** Lo escribió quien pregunta: solo su autor puede retirarlo del catálogo. */
  is_mine: boolean;
  category?: Category | null;
  likes?: number;
  liked_by_me?: boolean;
}

export type ItemPriority = 'baja' | 'media' | 'alta';

/**
 * Un regalo de una lista. `is_reserved` y `reserved_by_me` solo llegan a quien mira una lista
 * ajena: en la propia no vienen, y su ausencia significa «es tu lista», no «está libre».
 */
export interface WishlistItem {
  id: number;
  /** El nombre que le dio el dueño, o el del producto si no le dio uno. */
  name: string;
  notes: string | null;
  priority: ItemPriority;
  position: number;
  received: boolean;
  product?: Product;
  is_reserved?: boolean;
  reserved_by_me?: boolean;
}

export interface ItemChanges {
  alias: string | null;
  notes: string | null;
  priority: ItemPriority;
}

/** Un regalo nuevo: del catálogo (con `product_id`) o escrito a mano (con nombre y categoría). */
export interface NewItem {
  product_id?: number;
  name?: string;
  category_id?: number;
  description?: string | null;
  url?: string | null;
  reference_price?: number | null;
  image?: File | null;
  share_with_catalog?: boolean;
  alias?: string | null;
  notes?: string | null;
  priority: ItemPriority;
}

export interface WishlistInput {
  name: string;
  description: string | null;
  visibility: WishlistVisibility;
  event_date: string | null;
}

/** Una página de resultados, como la arma Laravel. */
export interface Page<T> {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}
