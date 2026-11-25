import { Signal, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map } from 'rxjs';
import { Counts } from '../core/api/models';

export interface Section {
  /** Adonde lleva tocarla. */
  url: string;
  title: string;
  icon: string;
  /**
   * Las rutas en que esta sección se marca como la actual. Notificaciones son dos pantallas
   * (Avisos y Solicitudes) bajo una misma sección.
   */
  paths: string[];
  /** Los contadores que suma su globito, si lleva. */
  counts?: (keyof Counts)[];
}

/**
 * Las secciones, en orden: el menú lateral en escritorio y la barra inferior en el teléfono son la
 * misma lista. Son cinco porque es lo que cabe cómodo en la barra inferior; por eso Personas vive
 * dentro de Gente (el buscador) y Avisos y Solicitudes, dentro de Notificaciones. Notificaciones va
 * al final: a la derecha en la barra inferior.
 */
export const SECTIONS: Section[] = [
  { url: '/listas', title: 'Mis listas', icon: 'gift-outline', paths: ['/listas'] },
  { url: '/descubrir', title: 'Descubrir', icon: 'compass-outline', paths: ['/descubrir'] },
  { url: '/reservas', title: 'Voy a regalar', icon: 'bag-handle-outline', paths: ['/reservas'] },
  { url: '/mi-gente', title: 'Gente', icon: 'people-outline', paths: ['/mi-gente'] },
  {
    url: '/avisos',
    title: 'Notificaciones',
    icon: 'notifications-outline',
    paths: ['/avisos', '/solicitudes'],
    counts: ['unread_notifications', 'pending_requests'],
  },
];

/** La sección de una ruta, o undefined en las pantallas de más adentro (una lista, editar...). */
export function sectionFor(path: string): Section | undefined {
  return SECTIONS.find((section) => section.paths.includes(path));
}

/** El número del globito de una sección: la suma de sus contadores. */
export function sectionCount(section: Section, counts: Counts): number {
  return (section.counts ?? []).reduce((total, key) => total + counts[key], 0);
}

/** La ruta actual, sin ?consulta ni #ancla, al día con cada navegación. */
export function injectCurrentPath(): Signal<string> {
  const router = inject(Router);
  const pathOf = (url: string) => url.split(/[?#]/)[0];
  return toSignal(
    router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => pathOf(router.url)),
    ),
    { initialValue: pathOf(router.url) },
  );
}
