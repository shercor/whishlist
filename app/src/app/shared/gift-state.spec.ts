import { WishlistItem } from '../core/api/models';
import { giftStateOf } from './gift-state';

function item(overrides: Partial<WishlistItem>): WishlistItem {
  return {
    id: 1,
    name: 'Dixit',
    notes: null,
    priority: 'media',
    position: 1,
    received: false,
    is_reserved: false,
    reserved_by_me: false,
    ...overrides,
  };
}

describe('giftStateOf', () => {
  it('distingue libre, mío y de otra persona', () => {
    expect(giftStateOf(item({}))).toBe('available');
    expect(giftStateOf(item({ is_reserved: true, reserved_by_me: true }))).toBe('mine');
    expect(giftStateOf(item({ is_reserved: true }))).toBe('taken');
  });

  it('recibido gana sobre reservado', () => {
    expect(giftStateOf(item({ received: true, is_reserved: true, reserved_by_me: true }))).toBe(
      'received',
    );
  });
});
