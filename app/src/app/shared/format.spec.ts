import {
  eventCountdown,
  formatAgo,
  formatEventDate,
  formatShortEventDate,
  giftCount,
} from './format';

describe('formatEventDate', () => {
  it('escribe la fecha del evento en español sin correrse de día', () => {
    expect(formatEventDate('2026-03-01')).toBe('1 de marzo de 2026');
    expect(formatEventDate('2026-12-31')).toBe('31 de diciembre de 2026');
    expect(formatShortEventDate('2026-09-20')).toBe('20 sept 2026');
  });
});

describe('giftCount', () => {
  it('nombra la lista vacía como un paso pendiente, no como «0 regalos»', () => {
    expect(giftCount(0)).toBe('Vacía todavía');
    expect(giftCount(1)).toBe('1 regalo');
    expect(giftCount(3)).toBe('3 regalos');
  });
});

describe('formatAgo', () => {
  it('dice hace cuánto, con la unidad más grande que cabe', () => {
    const now = Date.parse('2026-11-24T12:00:00Z');
    expect(formatAgo('2026-11-24T11:59:40Z', now)).toBe('ahora');
    expect(formatAgo('2026-11-24T11:55:00Z', now)).toBe('hace 5 minutos');
    expect(formatAgo('2026-11-23T12:00:00Z', now)).toBe('ayer');
    expect(formatAgo('2026-11-03T12:00:00Z', now)).toBe('hace 3 semanas');
  });
});

describe('eventCountdown', () => {
  // Las 23:30 de quien mira: a esa hora ya es mañana en UTC, y no debe restar un día.
  const today = new Date(2026, 10, 25, 23, 30);

  it('cuenta los días que faltan, con hoy y mañana por su nombre', () => {
    expect(eventCountdown('2026-11-25', today)).toEqual({ text: 'Hoy', upcoming: true });
    expect(eventCountdown('2026-11-26', today)).toEqual({ text: 'Mañana', upcoming: true });
    expect(eventCountdown('2026-12-24', today)).toEqual({ text: 'Faltan 29 días', upcoming: true });
  });

  it('dice hace cuánto fue si ya pasó', () => {
    expect(eventCountdown('2026-11-24', today)).toEqual({ text: 'Fue ayer', upcoming: false });
    expect(eventCountdown('2026-11-20', today)).toEqual({ text: 'Fue hace 5 días', upcoming: false });
  });
});
