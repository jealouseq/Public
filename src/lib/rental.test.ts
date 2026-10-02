import { describe, expect, it } from 'vitest';
import { addRentalDays, quote, safeDays, validateStartDate } from './rental';

describe('approved rental prices', () => {
  it.each([['ps5', 1, 600], ['ps5', 3, 1400], ['ps5', 7, 2500], ['ps5', 30, 6000], ['ps4', 3, 750], ['ps4', 7, 1200], ['ps4', 30, 2500]] as const)('%s for %i days costs %i UAH', (consoleId, days, amount) => expect(quote(consoleId, days).price).toBe(amount));
  it('rejects a PS4 one-day tariff', () => expect(() => quote('ps4', 1)).toThrow());
  it('moves one-day PS5 selection to three days when PS4 is selected', () => expect(safeDays('ps4', 1)).toBe(3));
  it('rejects unknown console IDs', () => expect(() => quote('ps6' as 'ps5', 3)).toThrow());
});

describe('calendar dates', () => {
  it('adds three days across the Kyiv daylight-saving boundary', () => expect(addRentalDays('2026-10-24', 3)).toBe('2026-10-27'));
  it('adds a day across leap day', () => expect(addRentalDays('2028-02-28', 1)).toBe('2028-02-29'));
  it('rejects impossible dates', () => expect(() => addRentalDays('2026-02-30', 3)).toThrow());
  it('rejects past start dates', () => expect(validateStartDate('2026-10-01', '2026-10-02')).toBe(false));
  it('accepts today', () => expect(validateStartDate('2026-10-02', '2026-10-02')).toBe(true));
});
