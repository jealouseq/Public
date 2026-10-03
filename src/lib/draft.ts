import { todayInKyiv, type ConsoleId } from './rental';

export type RentalDraft = { consoleId: ConsoleId; days: number; gameIds: string[]; start: string; controllers: number; method: 'delivery' | 'pickup'; step: 0 | 1 };
export const DRAFT_TTL = 30 * 60 * 1000;
const DRAFT_KEY = 'joyrent.rental-draft.v1';
export const emptyRentalDraft = (): RentalDraft => ({ consoleId: 'ps5', days: 3, gameIds: [], start: todayInKyiv(), controllers: 1, method: 'delivery', step: 0 });
export function serializeRentalDraft(draft: RentalDraft, now = Date.now()): string {
  // Explicit whitelist: contact details and consent never enter storage.
  const { consoleId, days, gameIds, start, controllers, method, step } = draft;
  return JSON.stringify({ version: 1, expiresAt: now + DRAFT_TTL, consoleId, days, gameIds, start, controllers, method, step });
}
export function parseRentalDraft(value: string | null, now = Date.now()): RentalDraft | null {
  try {
    const draft = JSON.parse(value ?? 'null');
    if (!draft || draft.version !== 1 || !Number.isFinite(draft.expiresAt) || draft.expiresAt <= now || draft.expiresAt > now + DRAFT_TTL || !['ps5', 'ps4'].includes(draft.consoleId) || !Number.isInteger(draft.days) || draft.days < 1 || draft.days > 30 || !Array.isArray(draft.gameIds) || !/^\d{4}-\d{2}-\d{2}$/.test(draft.start) || ![1, 2].includes(draft.controllers) || !['delivery', 'pickup'].includes(draft.method) || ![0, 1].includes(draft.step)) return null;
    return { consoleId: draft.consoleId, days: draft.days, gameIds: [...new Set<string>(draft.gameIds.filter((id: unknown) => typeof id === 'string'))].slice(0, 100), start: draft.start, controllers: draft.controllers, method: draft.method, step: draft.step };
  } catch { return null; }
}
export function readRentalDraft(): RentalDraft | null { try { return parseRentalDraft(sessionStorage.getItem(DRAFT_KEY)); } catch { return null; } }
export function saveRentalDraft(draft: RentalDraft): void { try { sessionStorage.setItem(DRAFT_KEY, serializeRentalDraft(draft)); } catch { /* Rental still works when browser storage is unavailable. */ } }
export function clearRentalDraft(): void { try { sessionStorage.removeItem(DRAFT_KEY); } catch { /* No stored draft to clear. */ } }
