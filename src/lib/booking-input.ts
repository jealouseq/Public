import type { RentalPayload } from './api';

// Keep these rules aligned with JR_Domain's canonical request intent.
export const trimRentalText = (value: string): string => value.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
export const normalizeRentalPhone = (value: string): string => value.replace(/[\t\n\r\f\v ()-]/g, '');
export const isUkrainianPhone = (value: string): boolean => /^(?:\+?380\d{9}|0\d{9})$/.test(normalizeRentalPhone(value));

// Empty is valid; null marks a malformed optional profile. Never accept arbitrary links.
export function normalizeTelegramContact(value: string): string | null {
  if (new TextEncoder().encode(value).length > 80 || /[\x00-\x1f\x7f]/.test(value)) return null;
  const text = value.replace(/^ +| +$/g, '');
  if (!text) return '';
  const profile = text.startsWith('https://t.me/') ? text.slice(13).replace(/\/$/, '') : text.replace(/^@/, '');
  return /^[A-Za-z0-9_]{5,32}$/.test(profile) ? '@' + profile.toLowerCase() : null;
}

export function canonicalRentalIntent(payload: Omit<RentalPayload, 'requestId'>) {
  const telegram = normalizeTelegramContact(payload.telegram ?? '');
  return {
    console: payload.console, days: payload.days, startDate: payload.startDate, controllers: payload.controllers,
    gameIds: [...new Set(payload.gameIds)].sort(), name: trimRentalText(payload.name), phone: normalizeRentalPhone(payload.phone),
    method: payload.method, address: payload.method === 'pickup' ? '' : trimRentalText(payload.address),
    securityMode: payload.securityMode ?? 'deposit', requestedGame: trimRentalText(payload.requestedGame ?? ''),
    ...(telegram ? { telegram } : {}),
  };
}
