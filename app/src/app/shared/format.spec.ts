import { formatEventDate, formatShortEventDate, giftCount } from './format';

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
