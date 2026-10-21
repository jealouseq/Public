import { describe, expect, it } from 'vitest';
import { controllerLabel, localPlayerLabel, gameLimitLabel, hasKnownInternetRequirement, dayGenitiveLabel } from './copy';

describe('visitor count labels', () => {
  it.each([
    [1, '1 дня', '1 дня'], [2, '2 днів', '2 дней'], [7, '7 днів', '7 дней'],
    [11, '11 днів', '11 дней'], [21, '21 дня', '21 дня'], [30, '30 днів', '30 дней'],
  ])('uses the genitive day form after a threshold of %i', (count, uk, ru) => {
    expect(dayGenitiveLabel(Number(count), 'uk')).toBe(uk);
    expect(dayGenitiveLabel(Number(count), 'ru')).toBe(ru);
  });
  it('uses complete controller labels in both recap languages', () => {
    expect([1, 2].map(count => controllerLabel(count, 'uk'))).toEqual(['1 геймпад', '2 геймпади']);
    expect([1, 2].map(count => controllerLabel(count, 'ru'))).toEqual(['1 геймпад', '2 геймпада']);
  });
  it('declines current-platform local-player counts correctly', () => {
    expect([1, 2, 4, 5].map(count => localPlayerLabel(count, 'uk'))).toEqual(['1 локальний гравець', '2 локальні гравці', '4 локальні гравці', '5 локальних гравців']);
    expect([1, 2, 4, 5].map(count => localPlayerLabel(count, 'ru'))).toEqual(['1 локальный игрок', '2 локальных игрока', '4 локальных игрока', '5 локальных игроков']);
  });
  it('uses the genitive game noun after a configured maximum', () => {
    expect([1, 2, 11, 21, 100].map(count => gameLimitLabel(count, 'uk'))).toEqual(['До 1 гри у бронюванні.', 'До 2 ігор у бронюванні.', 'До 11 ігор у бронюванні.', 'До 21 гри у бронюванні.', 'До 100 ігор у бронюванні.']);
    expect([1, 2, 11, 21, 100].map(count => gameLimitLabel(count, 'ru'))).toEqual(['До 1 игры в брони.', 'До 2 игр в брони.', 'До 11 игр в брони.', 'До 21 игры в брони.', 'До 100 игр в брони.']);
  });
});

describe('internet copy', () => {
  it('recognizes only the existing explicit requirement and preserves a flag note for custom descriptions', () => {
    expect(hasKnownInternetRequirement('Кампанія. Потрібне підключення до інтернету. Доступ уточнимо.', 'uk')).toBe(true);
    expect(hasKnownInternetRequirement('Кампания. Требуется подключение к интернету. Доступ уточним.', 'ru')).toBe(true);
    expect(hasKnownInternetRequirement('Грай із друзями.', 'uk')).toBe(false);
    expect(hasKnownInternetRequirement('Играй с друзьями.', 'ru')).toBe(false);
  });
});
