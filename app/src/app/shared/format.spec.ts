import { formatAgo, formatEventDate, formatShortEventDate, giftCount } from './format';

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
