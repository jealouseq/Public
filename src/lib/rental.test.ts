import { describe, expect, it } from 'vitest';
import { addRentalDays, dailyRate, dayLabel, quote, safeDays, validateStartDate, uniqueGames, localPlayers, matchesGameFilter, reconcileGameIds, deliveryCharge, isIntentionalSubmission } from './rental';
import { defaultCatalog } from './rental';

describe('approved rental prices', () => {
  it.each([['ps5', 1, 600], ['ps5', 3, 1400], ['ps5', 7, 2500], ['ps5', 30, 6000], ['ps4', 3, 750], ['ps4', 7, 1200], ['ps4', 30, 2500]] as const)('%s for %i days costs %i UAH', (consoleId, days, amount) => expect(quote(consoleId, days).price).toBe(amount));
  it('rejects a PS4 one-day tariff', () => expect(() => quote('ps4', 1)).toThrow());
  it('moves one-day PS5 selection to three days when PS4 is selected', () => expect(safeDays('ps4', 1)).toBe(3));
  it('rejects unknown console IDs', () => expect(() => quote('ps6' as 'ps5', 3)).toThrow());
});

describe('daily price comparison without changing the rental total', () => {
  it.each([
    [600, 1, 600, false], [1400, 3, 466.67, true], [2500, 7, 357.14, true],
    [6000, 30, 200, false], [750, 3, 250, false], [1200, 7, 171.43, true],
    [2500, 30, 83.33, true], [1499.5, 1, 1499.5, false], [1, 30, 0.03, true],
  ])('compares a configured %s UAH / %s day tariff', (price, days, amount, approximate) => {
    expect(dailyRate(Number(price), Number(days))).toEqual({ amount, approximate });
  });
  it('does not display a daily price for an unavailable or malformed tariff', () => {
    for (const [price, days] of [[600, 0], [600, -1], [600, 1.5], [-1, 3], [NaN, 3], [Infinity, 3]]) {
      expect(dailyRate(price, days)).toBeNull();
    }
  });
});

describe('published catalog and selection', () => {
  it('normalizes IDs before filters render and keeps the first published record', () => {
    const game = defaultCatalog.games[0];
    expect(uniqueGames([game, { ...game, title: 'duplicate' }, defaultCatalog.games[1]]).map(item => item.title)).toEqual([game.title, defaultCatalog.games[1].title]);
  });
  it('derives multiplayer filters from the currently selected platform', () => {
    const game = { ...defaultCatalog.games[0], players: 4, playersByPlatform: { ps5: 4, ps4: 2 }, filters: ['story'] };
    expect(localPlayers(game, 'ps4')).toBe(2);
    expect(matchesGameFilter(game, 'ps5', 'party')).toBe(true);
    expect(matchesGameFilter(game, 'ps4', 'party')).toBe(false);
    expect(matchesGameFilter(game, 'ps4', 'two')).toBe(true);
    expect(matchesGameFilter(game, 'ps4', 'story')).toBe(true);
  });
  it('drops unknown, duplicate and incompatible selections after a refresh', () => {
    const game = { ...defaultCatalog.games[0], platforms: ['ps5'] };
    expect(reconcileGameIds([game.id, game.id, 'missing'], [game], 'ps5')).toEqual([game.id]);
    expect(reconcileGameIds([game.id], [game], 'ps4')).toEqual([]);
  });
  it('chooses an available duration or leaves the unavailable console without a tariff', () => {
    const tariffs = { ps5: defaultCatalog.tariffs.ps5.filter(item => item.days === 7), ps4: [] };
    expect(safeDays('ps5', 3, tariffs)).toBe(7);
    expect(safeDays('ps4', 3, tariffs)).toBeNull();
  });
});

describe('booking intent and delivery', () => {
  it('requires the contact step and the explicit submit control', () => {
    expect(isIntentionalSubmission(0, 'submit')).toBe(false);
    expect(isIntentionalSubmission(1, 'continue')).toBe(false);
    expect(isIntentionalSubmission(1, undefined)).toBe(false);
    expect(isIntentionalSubmission(1, 'submit')).toBe(true);
  });
  it('keeps delivery pending until the address zone is confirmed, including long rentals', () => {
    expect(deliveryCharge('delivery', 3, 7, null)).toBeNull();
    expect(deliveryCharge('delivery', 7, 7, null)).toBeNull();
    expect(deliveryCharge('delivery', 30, 7, null)).toBeNull();
    expect(deliveryCharge('delivery', 3, 7, 300)).toBe(300);
    expect(deliveryCharge('delivery', 7, 7, 300)).toBeNull();
    expect(deliveryCharge('pickup', 3, 7, null)).toBe(0);
  });
});

describe('calendar dates', () => {
  it('adds three days across the Kyiv daylight-saving boundary', () => expect(addRentalDays('2026-10-24', 3)).toBe('2026-10-27'));
  it('adds a day across leap day', () => expect(addRentalDays('2028-02-28', 1)).toBe('2028-02-29'));
  it('rejects impossible dates', () => expect(() => addRentalDays('2026-02-30', 3)).toThrow());
  it('rejects past start dates', () => expect(validateStartDate('2026-10-01', '2026-10-02')).toBe(false));
  it('accepts today', () => expect(validateStartDate('2026-10-02', '2026-10-02')).toBe(true));
});

describe('Russian duration labels', () => {
  it('uses Russian inflection for all offered periods', () => {
    expect([1, 3, 7, 30].map(days => dayLabel(days, 'ru'))).toEqual(['1 день', '3 дня', '7 дней', '30 дней']);
  });
});

describe('configured duration labels', () => {
  it.each([
    [1, '1 день', '1 день'], [2, '2 дні', '2 дня'], [3, '3 дні', '3 дня'],
    [4, '4 дні', '4 дня'], [5, '5 днів', '5 дней'], [11, '11 днів', '11 дней'],
    [14, '14 днів', '14 дней'], [21, '21 день', '21 день'], [22, '22 дні', '22 дня'],
    [30, '30 днів', '30 дней'],
  ])('declines a %i-day tariff in both languages', (count, uk, ru) => {
    expect(dayLabel(Number(count), 'uk')).toBe(uk);
    expect(dayLabel(Number(count), 'ru')).toBe(ru);
  });
});
