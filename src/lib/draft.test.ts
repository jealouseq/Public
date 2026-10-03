import { describe, expect, it } from 'vitest';
import { parseRentalDraft, serializeRentalDraft, DRAFT_TTL, type RentalDraft } from './draft';

const draft: RentalDraft = { consoleId: 'ps4', days: 7, gameIds: ['game'], start: '2026-10-10', controllers: 2, method: 'delivery', step: 1 };

describe('short-lived non-personal rental draft', () => {
  it('restores rental choices within the session lifetime', () => expect(parseRentalDraft(serializeRentalDraft(draft, 1000), 1001)).toEqual(draft));
  it('expires after thirty minutes and ignores malformed entries', () => {
    expect(parseRentalDraft(serializeRentalDraft(draft, 1000), 1000 + DRAFT_TTL)).toBeNull();
    expect(parseRentalDraft('{oops}', 1001)).toBeNull();
    expect(parseRentalDraft(JSON.stringify({ ...draft, expiresAt: 2000, version: 1, consoleId: 'ps6' }), 1001)).toBeNull();
  });
  it('never serializes contact details or consent even if passed by a caller', () => {
    const stored = serializeRentalDraft({ ...draft, name: 'Private name', phone: '+380001234567', address: 'Private address', consent: true } as RentalDraft, 1000);
    expect(stored).not.toMatch(/Private|380001|name|phone|address|consent/);
  });
});
