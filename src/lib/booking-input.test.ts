import { describe, expect, it } from 'vitest';
import { canonicalRentalIntent, isUkrainianPhone, normalizeRentalPhone, trimRentalText } from './booking-input';
import type { RentalPayload } from './api';

const intent: Omit<RentalPayload, 'requestId'> = {
  console: 'ps5', days: 3, startDate: '2026-10-10', controllers: 2, gameIds: ['gt7', 'gta-v'],
  name: 'Тест', phone: '+380500000000', method: 'delivery', address: 'Фонтанська дорога, 10',
  securityMode: 'deposit', requestedGame: '', consent: true, website: '', language: 'uk',
};

describe('Ukrainian booking phone validation', () => {
  it.each(['+380500000000', '380500000000', '0500000000', '+380 (50) 000-00-00', '050 000 00 00'])('accepts the server-supported format %s', phone => {
    expect(isUkrainianPhone(phone)).toBe(true);
  });
  it.each(['abcdefghij', '+1 212 555 1234', '050000000', '05000000000', '++380500000000', '+38050.0000000', ''])('rejects the unsupported format %s', phone => {
    expect(isUkrainianPhone(phone)).toBe(false);
  });
  it('removes only the punctuation and ASCII whitespace that the server removes', () => {
    expect(normalizeRentalPhone(' +380\t(50)\n000-00-00\r')).toBe('+380500000000');
    expect(normalizeRentalPhone('+380\u00a0500000000')).toBe('+380\u00a0500000000');
  });
});

describe('canonical retry intent', () => {
  it('keeps identity when contact whitespace or phone formatting changes', () => {
    expect(canonicalRentalIntent({ ...intent, name: ' \tТест\r\n', phone: '+380 (50) 000-00-00', address: '  Фонтанська дорога, 10  ' })).toEqual(canonicalRentalIntent(intent));
  });
  it('treats the same game set in a different order as the same booking', () => {
    expect(canonicalRentalIntent({ ...intent, gameIds: ['gta-v', 'gt7', 'gt7'] })).toEqual(canonicalRentalIntent(intent));
  });
  it('omits language, consent and bot-trap values from the rental identity', () => {
    expect(canonicalRentalIntent({ ...intent, language: 'ru', consent: false, website: 'bot' })).toEqual(canonicalRentalIntent(intent));
  });
  it('treats omitted legacy defaults as deposit and an empty game wish', () => {
    expect(canonicalRentalIntent({ ...intent, securityMode: undefined, requestedGame: undefined })).toEqual(canonicalRentalIntent(intent));
  });
  it('ignores a retained delivery address for pickup', () => {
    expect(canonicalRentalIntent({ ...intent, method: 'pickup', address: 'another previous delivery address' })).toEqual(canonicalRentalIntent({ ...intent, method: 'pickup', address: '' }));
  });
  it.each([
    { phone: '+380500000001' }, { name: 'Інше ім’я' }, { address: 'Інша вулиця, 12' },
    { days: 7 }, { console: 'ps4' as const }, { startDate: '2026-10-11' }, { controllers: 1 },
    { gameIds: ['gt7'] }, { securityMode: 'contract' as const }, { requestedGame: 'Minecraft' }, { method: 'pickup' as const },
  ])('changes identity for a material rental change %j', patch => {
    expect(canonicalRentalIntent({ ...intent, ...patch })).not.toEqual(canonicalRentalIntent(intent));
  });
  it('trims a game wish using the PHP trim character set', () => {
    expect(canonicalRentalIntent({ ...intent, requestedGame: '\v Minecraft \0' })).toEqual(canonicalRentalIntent({ ...intent, requestedGame: 'Minecraft' }));
    expect(trimRentalText('\u00a0Тест\u00a0')).toBe('\u00a0Тест\u00a0');
  });
});
