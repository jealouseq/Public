import catalog from '../../wordpress/joyrent-rentals/data/catalog.json';

export type ConsoleId = 'ps5' | 'ps4';
export type Tariff = { days: number; price: number; name: string; description: string; nameRu?: string; descriptionRu?: string };
export type Game = typeof catalog.games[number];
export const defaultCatalog = catalog;
export const money = (value: number) => new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 }).format(value);
export const dayLabel = (days: number, language: 'uk' | 'ru' = 'uk') => language === 'ru' ? (days === 1 ? '1 день' : days === 3 ? '3 дня' : `${days} дней`) : days === 1 ? '1 день' : days === 3 ? '3 дні' : `${days} днів`;
export function quote(consoleId: ConsoleId, days: number, tariffs: Record<ConsoleId, Tariff[]> = catalog.tariffs): Tariff {
  const tariff = tariffs[consoleId]?.find(item => item.days === days);
  if (!tariff) throw new Error('Цей тариф недоступний.');
  return tariff;
}
export function safeDays(consoleId: ConsoleId, days: number): number {
  return catalog.tariffs[consoleId].some(item => item.days === days) ? days : 3;
}
function parseCalendarDate(date: string): Date {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) throw new Error('Некоректна дата.');
  const parsed = new Date(`${date}T12:00:00Z`);
  if (!Number.isFinite(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== date) throw new Error('Некоректна дата.');
  return parsed;
}
export function addRentalDays(date: string, days: number): string {
  if (!Number.isInteger(days) || days <= 0 || days > 30) throw new Error('Некоректний термін.');
  const parsed = parseCalendarDate(date);
  parsed.setUTCDate(parsed.getUTCDate() + days);
  return parsed.toISOString().slice(0, 10);
}
export function validateStartDate(date: string, today: string): boolean {
  try { parseCalendarDate(date); parseCalendarDate(today); return date >= today; } catch { return false; }
}
export function todayInKyiv(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Kyiv', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}
