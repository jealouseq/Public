import type { Language } from './i18n';

function formFor(count: number, forms: readonly [string, string, string]): string {
  const value = Math.abs(Math.trunc(count));
  if (value % 100 >= 11 && value % 100 <= 14) return forms[2];
  return value % 10 === 1 ? forms[0] : value % 10 >= 2 && value % 10 <= 4 ? forms[1] : forms[2];
}
export function dayCountLabel(count: number, language: Language): string {
  return `${count} ${formFor(count, language === 'ru' ? ['день', 'дня', 'дней'] : ['день', 'дні', 'днів'])}`;
}
export function dayGenitiveLabel(count: number, language: Language): string {
  return `${count} ${formFor(count, language === 'ru' ? ['дня', 'дней', 'дней'] : ['дня', 'днів', 'днів'])}`;
}
export function controllerLabel(count: number, language: Language): string {
  return `${count} ${formFor(count, language === 'ru' ? ['геймпад', 'геймпада', 'геймпадов'] : ['геймпад', 'геймпади', 'геймпадів'])}`;
}
export function localPlayerLabel(count: number, language: Language): string {
  return `${count} ${formFor(count, language === 'ru' ? ['локальный игрок', 'локальных игрока', 'локальных игроков'] : ['локальний гравець', 'локальні гравці', 'локальних гравців'])}`;
}
export function gameLimitLabel(count: number, language: Language): string {
  const singular = count % 10 === 1 && count % 100 !== 11;
  return language === 'ru' ? `До ${count} ${singular ? 'игры' : 'игр'} в брони.` : `До ${count} ${singular ? 'гри' : 'ігор'} у бронюванні.`;
}
export function hasKnownInternetRequirement(description: string, language: Language): boolean {
  return description.includes(language === 'ru' ? 'Требуется подключение к интернету.' : 'Потрібне підключення до інтернету.');
}
