import type { RentalPayload } from './api';

// Keep these rules aligned with JR_Domain's canonical request intent.
export const trimRentalText = (value: string): string => value.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
export const normalizeRentalPhone = (value: string): string => value.replace(/[\t\n\r\f\v ()-]/g, '');
export const isUkrainianPhone = (value: string): boolean => /^(?:\+?380\d{9}|0\d{9})$/.test(normalizeRentalPhone(value));

export function canonicalRentalIntent(payload: Omit<RentalPayload, 'requestId'>) {
  return {
    console: payload.console, days: payload.days, startDate: payload.startDate, controllers: payload.controllers,
    gameIds: [...new Set(payload.gameIds)].sort(), name: trimRentalText(payload.name), phone: normalizeRentalPhone(payload.phone),
    method: payload.method, address: payload.method === 'pickup' ? '' : trimRentalText(payload.address),
    securityMode: payload.securityMode ?? 'deposit', requestedGame: trimRentalText(payload.requestedGame ?? ''),
  };
}
