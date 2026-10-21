import { describe, expect, it } from 'vitest';
import { addressWithStreet, findOdesaStreets, hasCompleteOdesaStreet } from './odesa-streets';

describe('local Odesa address suggestions', () => {
  it('finds Fontanska from either language and displays the selected UI language', () => {
    expect(findOdesaStreets('Фонтанская', 'ru')[0]?.label).toBe('Фонтанская дорога');
    expect(findOdesaStreets('Фонтанская', 'uk')[0]?.label).toBe('Фонтанська дорога');
    expect(findOdesaStreets('Фонтанська', 'ru')[0]?.label).toBe('Фонтанская дорога');
  });

  it('recognizes a street prefix and preserves the already entered house and apartment', () => {
    const input = 'ул. Фонтанская 12Б, кв. 4';
    const result = findOdesaStreets(input, 'ru')[0];
    expect(result?.label).toBe('Фонтанская дорога');
    expect(addressWithStreet(input, result!.label)).toBe('Фонтанская дорога, 12Б, кв. 4');
  });

  it('keeps a comma-separated house suffix while changing to the Ukrainian street name', () => {
    expect(addressWithStreet('Фонтанская, буд. 15/2, кв. 7', 'Фонтанська дорога'))
      .toBe('Фонтанська дорога, буд. 15/2, кв. 7');
  });

  it('supports reordered street words without showing unbounded results', () => {
    expect(findOdesaStreets('дорога фонтан', 'uk', 3)[0]?.label).toBe('Фонтанська дорога');
    expect(findOdesaStreets('вулиця', 'uk', 3)).toHaveLength(0);
    expect(findOdesaStreets('ф', 'uk')).toHaveLength(0);
    expect(findOdesaStreets('несуществующая улица', 'ru')).toHaveLength(0);
  });

  it('returns no duplicate streets and handles punctuation in Ukrainian names', () => {
    const results = findOdesaStreets("М'ясоєдовська", 'uk');
    expect(results.map(item => item.label)).toContain('Мʼясоєдовська вулиця');
    expect(new Set(results.map(item => item.id)).size).toBe(results.length);
  });

  it('recognizes a complete street in either language without treating a shortened name as complete', () => {
    expect(hasCompleteOdesaStreet('Фонтанская дорога, 12')).toBe(true);
    expect(hasCompleteOdesaStreet('Фонтанська дорога 12Б, кв. 4')).toBe(true);
    expect(hasCompleteOdesaStreet('  ФОНТАНСКАЯ   ДОРОГА, буд. 15/2, кв. 7')).toBe(true);
    expect(hasCompleteOdesaStreet('Фонтанская, 12')).toBe(false);
    expect(hasCompleteOdesaStreet('Фонтанська, 12, кв. 4')).toBe(false);
    expect(hasCompleteOdesaStreet('Фонтанская дорога новая, 12')).toBe(false);
  });
});
