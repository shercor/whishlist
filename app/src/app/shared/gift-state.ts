import { WishlistItem } from '../core/api/models';

export type GiftState = 'available' | 'mine' | 'taken' | 'received';

/**
 * Cómo ve un regalo quien va a regalar. Los mismos textos que GiftState en la API.
 * Recibido gana sobre reservado: si ya lo tiene, da igual quién lo hubiera tomado.
 */
export function giftStateOf(item: WishlistItem): GiftState {
  if (item.received) {
    return 'received';
  }
  if (item.reserved_by_me) {
    return 'mine';
  }
  return item.is_reserved ? 'taken' : 'available';
}

/** Texto y colores del chip. Que esté tomado se dice; por quién, nunca. */
export const GIFT_STATES: Record<GiftState, { title: string; chip: string }> = {
  available: {
    title: 'Disponible',
    chip: 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300',
  },
  mine: {
    title: 'Lo reservaste tú',
    chip: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  },
  taken: {
    title: 'Ya lo reservaron',
    chip: 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
  },
  received: {
    title: 'Ya lo tiene',
    chip: 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300',
  },
};
