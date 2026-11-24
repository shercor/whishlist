import { AccessSource, AccessStatus } from '../core/api/models';

/** Los mismos textos que AccessRequestStatus::title() en la API, con el color de su chip. */
export const ACCESS_STATUSES: Record<AccessStatus, { title: string; chip: string }> = {
  pendiente: {
    title: 'Pendiente',
    chip: 'bg-amber-50 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
  },
  aprobado: {
    title: 'Aprobada',
    chip: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  },
  rechazado: {
    title: 'Rechazada',
    chip: 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300',
  },
  revocado: {
    title: 'Revocada',
    chip: 'bg-neutral-100 text-neutral-600 dark:bg-white/10 dark:text-neutral-300',
  },
};

/** Por dónde entró: los mismos textos que AccessSource::title(). */
export const ACCESS_SOURCES: Record<AccessSource, string> = {
  invitacion: 'Lo invitaste',
  solicitud: 'Te lo pidió',
  enlace: 'Entró con el enlace',
};
