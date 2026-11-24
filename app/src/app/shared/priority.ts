import { ItemPriority } from '../core/api/models';

/** Los mismos textos que ItemPriority::title() y hint() en la API, de menor a mayor. */
export const PRIORITIES: { value: ItemPriority; title: string; hint: string }[] = [
  { value: 'baja', title: 'Baja', hint: 'Me gustaría, pero sin apuro.' },
  { value: 'media', title: 'Media', hint: 'Me haría ilusión.' },
  { value: 'alta', title: 'Alta', hint: 'Es lo que más quiero.' },
];

export function priorityOf(value: ItemPriority) {
  return PRIORITIES.find((option) => option.value === value) ?? PRIORITIES[1];
}
