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
  /** Solo en el perfil propio: el nombre real, el correo y los ajustes de privacidad. */
  name?: string;
  email?: string;
  show_name?: boolean;
  is_private?: boolean;
}

/** Lo que el menú cuenta: avisos sin leer y solicitudes por responder. */
export interface Counts {
  unread_notifications: number;
  pending_requests: number;
}

export interface ProfileChanges {
  name?: string;
  username?: string;
  show_name?: boolean;
  is_private?: boolean;
}

export interface AppNotification {
  id: string;
  title: string | null;
  detail: string | null;
  /** Enlace a la pantalla de la web; la app lo traduce con appPathFor(). */
  url: string | null;
  read: boolean;
  created_at: string | null;
}

export interface Follow {
  id: number;
  status: FollowStatus;
  follower?: User;
  followed?: User;
}

export interface FollowLists {
  pending_for_me: Follow[];
  followers: Follow[];
  following: Follow[];
  my_pending: Follow[];
}

export type AccessStatus = 'pendiente' | 'aprobado' | 'rechazado' | 'revocado';
export type AccessSource = 'invitacion' | 'solicitud' | 'enlace';

/** Un permiso sobre una lista privada. En lo que pedí yo, sin nombre de lista si no me la dieron. */
export interface WishlistAccess {
  id: number;
  status: AccessStatus;
  source: AccessSource;
  message: string | null;
  responded_at: string | null;
  user?: User;
  wishlist?: Pick<Wishlist, 'id' | 'owner'> & Partial<Wishlist>;
}

/** El cuerpo del 403 de una lista privada que no puedo ver. Nunca trae su nombre. */
export interface LockedWishlist {
  owner: User;
  access_status: AccessStatus | null;
  can_request: boolean;
}

export interface LoginResponse {
  token: string;
  user: User;
}

/** Lo que pide `POST /users` para crear una cuenta (sin el nombre del dispositivo). */
export interface NewAccount {
  name: string;
  username: string;
  email: string;
  password: string;
  password_confirmation: string;
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
  /** El id de mi reserva, para soltarla. Nunca el de la reserva de otra persona. */
  my_reservation_id?: number | null;
}

export type FollowStatus = 'pendiente' | 'aceptado';

/** El perfil de alguien, con exactamente las listas que puedo abrir. */
export interface UserProfile extends User {
  is_private: boolean;
  /** Cómo lo sigo yo: null si no lo sigo ni se lo pedí. */
  follow_status: FollowStatus | null;
  /** Si veo su perfil entero o está cerrado para mí. */
  profile_open: boolean;
  wishlists: Wishlist[];
}

/** Una reserva mía («Voy a regalar»). */
export interface Reservation {
  id: number;
  note: string | null;
  /** Fecha y hora ISO en que se suelta sola. */
  expires_at: string | null;
  item: WishlistItem;
  /** La lista del regalo, con su dueño. */
  wishlist?: Wishlist;
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
