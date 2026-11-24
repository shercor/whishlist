import { WishlistVisibility } from '../core/api/models';

/** Los mismos textos que WishlistVisibility::title() y description() en la API. */
export const VISIBILITIES: {
  value: WishlistVisibility;
  title: string;
  description: string;
  icon: string;
}[] = [
  {
    value: 'privada',
    title: 'Privada',
    description: 'Solo quien tú invites o quien tenga su enlace.',
    icon: 'lock-closed-outline',
  },
  {
    value: 'publica',
    title: 'Pública',
    description: 'Cualquiera puede encontrarla, salvo que tu perfil sea privado.',
    icon: 'globe-outline',
  },
];

export function visibilityOf(value: WishlistVisibility) {
  return VISIBILITIES.find((option) => option.value === value) ?? VISIBILITIES[0];
}
