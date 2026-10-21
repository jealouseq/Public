import catalog from '../../wordpress/joyrent-rentals/data/catalog.json';
import { dayCountLabel } from './copy';

export type ConsoleId = 'ps5' | 'ps4';
export type Tariff = { days: number; price: number; name: string; description: string; nameRu?: string; descriptionRu?: string };
export type Game = Omit<typeof catalog.games[number], 'playersByPlatform' | 'requiresInternet'> & { playersByPlatform?: Partial<Record<ConsoleId, number>>; requiresInternet?: boolean; imageUrl?: string };
export const defaultCatalog = catalog;
export const money = (value: number) => new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 2 }).format(value);
export const dayLabel = (days: number, language: 'uk' | 'ru' = 'uk') => dayCountLabel(days, language);
/** A comparison only: the configured period price remains the amount charged. */
export function dailyRate(price: number, days: number): { amount: number; approximate: boolean } | null {
  if (!Number.isFinite(price) || price < 0 || !Number.isInteger(days) || days <= 0) return null;
  const exact = price / days;
  const amount = Math.round(exact * 100) / 100;
  return { amount, approximate: Math.abs(amount - exact) > 1e-8 };
}
export function quote(consoleId: ConsoleId, days: number, tariffs: Record<ConsoleId, Tariff[]> = catalog.tariffs): Tariff {
  const tariff = tariffs[consoleId]?.find(item => item.days === days);
  if (!tariff) throw new Error('Цей тариф недоступний.');
  return tariff;
}
export function safeDays(consoleId: ConsoleId, days: number, tariffs: Record<ConsoleId, Tariff[]> = catalog.tariffs): number | null {
  const available = tariffs[consoleId] ?? [];
  return available.some(item => item.days === days) ? days : available.find(item => item.days === 3)?.days ?? available[0]?.days ?? null;
}
export function uniqueGames(games: Game[]): Game[] {
  const seen = new Set<string>();
  return games.filter(game => typeof game.id === 'string' && game.id.length > 0 && !seen.has(game.id) && Boolean(seen.add(game.id)));
}
export function localPlayers(game: Game, consoleId: ConsoleId): number { return game.playersByPlatform?.[consoleId] ?? game.players; }
export function matchesGameFilter(game: Game, consoleId: ConsoleId, filter: string): boolean {
  if (!game.platforms.includes(consoleId)) return false;
  if (filter === 'all') return true;
  if (filter === 'two') return localPlayers(game, consoleId) >= 2;
  if (filter === 'party') return localPlayers(game, consoleId) >= 3;
  return game.filters.includes(filter);
}
export function reconcileGameIds(ids: string[], games: Game[], consoleId: ConsoleId, limit = 100): string[] {
  const valid = new Set(games.filter(game => game.platforms.includes(consoleId)).map(game => game.id));
  return [...new Set(ids)].filter(id => valid.has(id)).slice(0, Math.max(0, Math.min(100, limit)));
}
export function deliveryCharge(method: 'delivery' | 'pickup', days: number, freeFrom: number, fee: number | null): number | null {
  if (method === 'pickup') return 0;
  // Long-rental delivery is free only in the approved green/yellow zones.
  // The form does not classify an address, so its delivery amount stays pending.
  return days >= freeFrom ? null : fee;
}
export function isIntentionalSubmission(step: number, intent: string | undefined): boolean { return step === 1 && intent === 'submit'; }
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
