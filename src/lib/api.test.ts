import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import type { RentalPayload, RequestReceipt } from './api';

let api: typeof import('./api');
const browser = {
  JOYRENT: { apiBase: '/wp-api/joyrent/v1', assetBase: '', nonce: 'test-wp-nonce' },
  location: { search: '' },
};
const payload: RentalPayload = {
  language: 'uk', console: 'ps5', days: 3, startDate: '2026-10-10', controllers: 2, gameIds: [],
  name: 'Тест', phone: '+380991234567', method: 'delivery', address: 'Тестова, 1',
  consent: true, website: '', requestId: 'same-request-after-lost-response',
};
const receipt: RequestReceipt = { reference: 'JR-TEST', rentalAmount: 1400, status: 'awaiting_confirmation' };
const uncertainUk = 'Не вдалося підтвердити бронювання. Повтори спробу з тими самими даними або зв’яжися з нами.';
const uncertainRu = 'Не удалось подтвердить бронь. Повтори попытку с теми же данными или свяжись с нами.';

beforeAll(async () => {
  vi.stubGlobal('window', browser);
  api = await import('./api');
});
beforeEach(() => {
  vi.useFakeTimers();
  browser.location.search = '';
});
afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.stubGlobal('window', browser);
});

describe('bounded API requests', () => {
  it('stops a pending submission at thirty seconds and aborts the network request', async () => {
    let signal: AbortSignal | undefined;
    vi.stubGlobal('fetch', (_url: string, init: RequestInit) => {
      signal = init.signal ?? undefined;
      return new Promise<Response>(() => {});
    });
    let settled = false;
    const outcome = api.submitRequest(payload).catch(error => error).finally(() => { settled = true; });

    await vi.advanceTimersByTimeAsync(29_999);
    expect(settled).toBe(false);
    await vi.advanceTimersByTimeAsync(1);
    expect(settled).toBe(true);
    expect(signal?.aborted).toBe(true);
    expect((await outcome).message).toBe(uncertainUk);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('keeps the original deadline when headers arrive but the receipt body stalls', async () => {
    let signal: AbortSignal | undefined;
    let sendHeaders!: (response: Response) => void;
    vi.stubGlobal('fetch', (_url: string, init: RequestInit) => {
      signal = init.signal ?? undefined;
      return new Promise<Response>(resolve => { sendHeaders = resolve; });
    });
    let settled = false;
    const outcome = api.submitRequest(payload).catch(error => error).finally(() => { settled = true; });

    await vi.advanceTimersByTimeAsync(20_000);
    sendHeaders(new Response(new ReadableStream({ start(controller) { controller.enqueue(new TextEncoder().encode('{')); } })));
    await vi.advanceTimersByTimeAsync(9_999);
    expect(settled).toBe(false);
    await vi.advanceTimersByTimeAsync(1);
    expect(settled).toBe(true);
    expect(signal?.aborted).toBe(true);
    expect((await outcome).message).toBe(uncertainUk);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('clears the deadline after success and retains JSON headers and the WordPress nonce', async () => {
    let sent: RequestInit | undefined;
    vi.stubGlobal('fetch', (_url: string, init: RequestInit) => {
      sent = init;
      return Promise.resolve(Response.json(receipt));
    });

    await expect(api.submitRequest(payload)).resolves.toEqual(receipt);
    expect(vi.getTimerCount()).toBe(0);
    await vi.advanceTimersByTimeAsync(60_000);
    expect(sent?.signal?.aborted).toBe(false);
    const headers = new Headers(sent?.headers);
    expect(headers.get('Content-Type')).toBe('application/json');
    expect(headers.get('X-WP-Nonce')).toBe('test-wp-nonce');
    expect(JSON.parse(String(sent?.body))).toEqual(payload);
  });

  it('also bounds catalog loading and retains its existing failure guidance', async () => {
    vi.stubGlobal('fetch', () => new Promise<Response>(() => {}));
    let settled = false;
    const outcome = api.getCatalog().catch(error => error).finally(() => { settled = true; });

    await vi.advanceTimersByTimeAsync(30_000);
    expect(settled).toBe(true);
    expect((await outcome).message).toBe('Не вдалося оформити бронювання. Перевір з’єднання та спробуй ще раз.');
    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('uncertain submission outcomes', () => {
  it.each([['uk', uncertainUk], ['ru', uncertainRu]] as const)('gives %s retry guidance after a network failure', async (language, message) => {
    browser.location.search = language === 'ru' ? '?lang=ru' : '';
    vi.stubGlobal('fetch', () => Promise.reject(new TypeError('Connection lost')));

    await expect(api.submitRequest({ ...payload, language })).rejects.toThrow(message);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('reports an unreadable receipt as uncertain instead of returning a false success', async () => {
    vi.stubGlobal('fetch', () => Promise.resolve(new Response(new ReadableStream({
      start(controller) { controller.error(new TypeError('Connection lost while reading receipt')); },
    }))));

    await expect(api.submitRequest(payload)).rejects.toThrow(uncertainUk);
    expect(vi.getTimerCount()).toBe(0);
  });

  it('preserves the caller request ID when the same details are retried after a lost response', async () => {
    const submitted: RentalPayload[] = [];
    vi.stubGlobal('fetch', (_url: string, init: RequestInit) => {
      submitted.push(JSON.parse(String(init.body)));
      return submitted.length === 1 ? Promise.reject(new TypeError('Connection lost')) : Promise.resolve(Response.json(receipt));
    });

    await api.submitRequest(payload).catch(() => {});
    await expect(api.submitRequest(payload)).resolves.toEqual(receipt);
    expect(submitted).toEqual([payload, payload]);
    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('received API errors', () => {
  it('keeps a matching-language server validation message and clears the deadline', async () => {
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json({ code: 'jr_invalid_phone', message: 'Перевір номер телефону.' }, { status: 400 })));

    await expect(api.submitRequest(payload)).rejects.toThrow('Перевір номер телефону.');
    expect(vi.getTimerCount()).toBe(0);
  });

  it('retains generic catalog network failure wording', async () => {
    vi.stubGlobal('fetch', () => Promise.reject(new TypeError('Connection lost')));

    await expect(api.getCatalog()).rejects.toThrow('Не вдалося оформити бронювання. Перевір з’єднання та спробуй ще раз.');
    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('verified booking receipts', () => {
  it.each([
    null, {}, { code: 'jr_create', message: 'Server error in an OK response' },
    { ...receipt, reference: '' }, { ...receipt, reference: '   ' }, { ...receipt, reference: 'x'.repeat(101) },
    { ...receipt, rentalAmount: -1 }, { ...receipt, rentalAmount: null }, { ...receipt, rentalAmount: '1400' },
    { ...receipt, status: 'completed' },
  ])('rejects an invalid successful response as uncertain: %j', async data => {
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json(data)));
    await expect(api.submitRequest(payload)).rejects.toThrow(uncertainUk);
    expect(vi.getTimerCount()).toBe(0);
  });
  it('localizes an invalid receipt and retains the caller request identity for retry', async () => {
    browser.location.search = '?lang=ru';
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json({})));
    await expect(api.submitRequest({ ...payload, language: 'ru' })).rejects.toThrow(uncertainRu);
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json(receipt)));
    await expect(api.submitRequest({ ...payload, language: 'ru' })).resolves.toEqual(receipt);
  });
  it('accepts a finite zero amount supported by configured tariffs', async () => {
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json({ ...receipt, rentalAmount: 0 })));
    await expect(api.submitRequest(payload)).resolves.toEqual({ ...receipt, rentalAmount: 0 });
  });
  it.each([
    { code: 123, message: 'Bad shape' }, { code: 'jr_validation', message: {} },
    { code: 'jr_validation', message: '' }, null,
  ])('uses safe localized guidance for malformed error bodies: %j', async data => {
    vi.stubGlobal('fetch', () => Promise.resolve(Response.json(data, { status: 400 })));
    await expect(api.submitRequest(payload)).rejects.toThrow('Не вдалося оформити бронювання. Перевір з’єднання та спробуй ще раз.');
  });
});
