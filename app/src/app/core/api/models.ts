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
