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
