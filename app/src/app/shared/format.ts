const eventDate = new Intl.DateTimeFormat('es-CL', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  // La fecha del evento no tiene hora: se lee en UTC para que no cambie de día según la zona.
  timeZone: 'UTC',
});

const shortEventDate = new Intl.DateTimeFormat('es-CL', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: 'UTC',
});

/** «15 de marzo de 2026» a partir de «2026-03-15». */
export function formatEventDate(date: string): string {
  return eventDate.format(new Date(`${date}T00:00:00Z`));
}

/** «15 mar 2026»: para el teléfono, donde la fecha larga no cabe en la fila. */
export function formatShortEventDate(date: string): string {
  return shortEventDate.format(new Date(`${date}T00:00:00Z`));
}

/**
 * Cuántos días faltan para el evento, contados en días del calendario de quien mira: «Hoy»,
 * «Mañana», «Faltan 12 días», «Fue ayer», «Fue hace 3 días». `upcoming` dice si todavía no pasa.
 */
export function eventCountdown(
  date: string,
  today = new Date(),
): { text: string; upcoming: boolean } {
  const [year, month, day] = date.split('-').map(Number);
  const event = Date.UTC(year, month - 1, day);
  const now = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  const days = Math.round((event - now) / 86_400_000);

  if (days === 0) {
    return { text: 'Hoy', upcoming: true };
  }
  if (days === 1) {
    return { text: 'Mañana', upcoming: true };
  }
  if (days > 1) {
    return { text: `Faltan ${days} días`, upcoming: true };
  }
  return { text: days === -1 ? 'Fue ayer' : `Fue hace ${-days} días`, upcoming: false };
}

/** «Vacía todavía», «1 regalo», «3 regalos». */
export function giftCount(count: number): string {
  if (count === 0) {
    return 'Vacía todavía';
  }

  return count === 1 ? '1 regalo' : `${count} regalos`;
}

/** «$12.990» o «US$12,50». La API manda el precio en la unidad normal de la moneda. */
export function formatPrice(amount: number | string, currency: string | null): string {
  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: currency ?? 'CLP',
    maximumFractionDigits: (currency ?? 'CLP') === 'CLP' ? 0 : 2,
  }).format(Number(amount));
}

const day = new Intl.DateTimeFormat('es-CL', { day: 'numeric', month: 'long' });

/** «12 de diciembre» a partir de una fecha y hora ISO, en la zona de quien mira. */
export function formatDay(iso: string): string {
  return day.format(new Date(iso));
}

/**
 * Cuándo se suelta sola una reserva, en días del calendario de quien mira: «Se suelta hoy»,
 * «Se suelta mañana», «Se suelta en 5 días». `soon` dice si quedan 3 días o menos, o si ya venció:
 * es cuando conviene avisar.
 */
export function releaseCountdown(iso: string, now = new Date()): { text: string; soon: boolean } {
  const expires = new Date(iso);
  if (expires.getTime() <= now.getTime()) {
    return { text: 'Se suelta en cualquier momento', soon: true };
  }
  const startOf = (date: Date) =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();
  // Redondeado: con cambio de horario un día dura 23 o 25 horas.
  const days = Math.round((startOf(expires) - startOf(now)) / 86_400_000);
  if (days === 0) {
    return { text: 'Se suelta hoy', soon: true };
  }
  if (days === 1) {
    return { text: 'Se suelta mañana', soon: true };
  }
  return { text: `Se suelta en ${days} días`, soon: days <= 3 };
}

const relative = new Intl.RelativeTimeFormat('es-CL', { numeric: 'auto' });
const STEPS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
];

/** «hace 5 minutos», «ayer», «hace 3 semanas». Menos de un minuto es «ahora». */
export function formatAgo(iso: string, now = Date.now()): string {
  const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
  for (const [unit, size] of STEPS) {
    if (Math.abs(seconds) >= size) {
      return relative.format(Math.round(seconds / size), unit);
    }
  }
  return 'ahora';
}
