import { describe, expect, it } from 'vitest';
import { matchesGameQuery } from './game-search';

describe('local game search', () => {
  it('recognizes the familiar FIFA and GTA names in both languages', () => {
    expect(matchesGameQuery('EA SPORTS FC 27', 'фифа')).toBe(true);
    expect(matchesGameQuery('EA SPORTS FC 27', 'фіфа')).toBe(true);
    expect(matchesGameQuery('Grand Theft Auto V', 'гта')).toBe(true);
    expect(matchesGameQuery('EA SPORTS UFC 6', 'фифа')).toBe(false);
  });
  it('matches words regardless of case, accents and punctuation', () => {
    expect(matchesGameQuery('Marvel’s Spider-Man 2', 'SPIDER man')).toBe(true);
    expect(matchesGameQuery('S.T.A.L.K.E.R. 2: Heart of Chornobyl', 'stalker')).toBe(true);
    expect(matchesGameQuery('EA SPORTS FC 27', 'fc 25')).toBe(false);
    expect(matchesGameQuery('It Takes Two', '')).toBe(true);
    expect(matchesGameQuery('It Takes Two', 'nothing')).toBe(false);
  });
});
