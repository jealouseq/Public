import { describe, expect, it } from 'vitest';
import { controllerLabel, localPlayerLabel, gameLimitLabel, hasKnownInternetRequirement } from './copy';

describe('visitor count labels', () => {
  it('uses complete controller labels in both recap languages', () => {
    expect([1, 2].map(count => controllerLabel(count, 'uk'))).toEqual(['1 геймпад', '2 геймпади']);
    expect([1, 2].map(count => controllerLabel(count, 'ru'))).toEqual(['1 геймпад', '2 геймпада']);
  });
  it('declines current-platform local-player counts correctly', () => {
    expect([1, 2, 4, 5].map(count => localPlayerLabel(count, 'uk'))).toEqual(['1 локальний гравець', '2 локальні гравці', '4 локальні гравці', '5 локальних гравців']);
    expect([1, 2, 4, 5].map(count => localPlayerLabel(count, 'ru'))).toEqual(['1 локальный игрок', '2 локальных игрока', '4 локальных игрока', '5 локальных игроков']);
  });
  it('uses the genitive game noun after a configured maximum', () => {
    expect([1, 2, 11, 21, 100].map(count => gameLimitLabel(count, 'uk'))).toEqual(['До 1 гри у заявці.', 'До 2 ігор у заявці.', 'До 11 ігор у заявці.', 'До 21 гри у заявці.', 'До 100 ігор у заявці.']);
    expect([1, 2, 11, 21, 100].map(count => gameLimitLabel(count, 'ru'))).toEqual(['До 1 игры в заявке.', 'До 2 игр в заявке.', 'До 11 игр в заявке.', 'До 21 игры в заявке.', 'До 100 игр в заявке.']);
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
