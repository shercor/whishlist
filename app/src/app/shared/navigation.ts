import { Counts } from '../core/api/models';

export interface Section {
  url: string;
  title: string;
  icon: string;
  /** El contador que se muestra al lado, si lo tiene. */
  count?: keyof Counts;
}

/** Las secciones del menú, en orden. Una sección se agrega aquí cuando su pantalla ya existe. */
export const SECTIONS: Section[] = [
  { url: '/listas', title: 'Mis listas', icon: 'gift-outline' },
  { url: '/descubrir', title: 'Descubrir', icon: 'compass-outline' },
  { url: '/personas', title: 'Personas', icon: 'search-outline' },
  { url: '/mi-gente', title: 'Mi gente', icon: 'people-outline' },
  { url: '/reservas', title: 'Voy a regalar', icon: 'bag-handle-outline' },
  {
    url: '/solicitudes',
    title: 'Solicitudes',
    icon: 'mail-unread-outline',
    count: 'pending_requests',
  },
  {
    url: '/avisos',
    title: 'Avisos',
    icon: 'notifications-outline',
    count: 'unread_notifications',
  },
];
