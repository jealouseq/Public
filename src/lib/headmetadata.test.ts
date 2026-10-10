import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import type { ReactElement } from 'react';

const runtime = vi.hoisted(() => ({
  state: undefined as 'uk' | 'ru' | undefined,
  effects: [] as (() => unknown)[],
  ref: undefined as { current: 'uk' | 'ru' | null } | undefined,
  boot: {} as { homeMetadata?: Partial<Record<'uk' | 'ru', { title: string; description: string; url: string; locale: string }>> },
}));
vi.mock('./api', () => ({ boot: runtime.boot }));
vi.mock('react', async importOriginal => ({
  ...await importOriginal<typeof import('react')>(),
  useState: (initialValue: () => 'uk' | 'ru') => {
    runtime.state ??= initialValue();
    return [runtime.state, (value: 'uk' | 'ru') => { runtime.state = value; }];
  },
  useRef: (initialValue: 'uk' | 'ru' | null) => runtime.ref ??= { current: initialValue },
  useEffect: (effect: () => unknown) => { runtime.effects.push(effect); },
}));
import { LanguageProvider } from './i18n';

const metadata = {
  uk: { title: 'JOYRENT — оренда в Одесі', description: 'Узгоджена українська інформація магазину.', url: 'https://joyrent.example/', locale: 'uk_UA' },
  ru: { title: 'JOYRENT — аренда в Одессе', description: 'Согласованная русская информация магазина.', url: 'https://joyrent.example/?lang=ru', locale: 'ru_UA' },
};
const selectors = {
  description: 'meta[name="description"]',
  canonical: 'link[rel="canonical"]',
  ogTitle: 'meta[property="og:title"]',
  ogDescription: 'meta[property="og:description"]',
  ogUrl: 'meta[property="og:url"]',
  ogLocale: 'meta[property="og:locale"]',
  ogOtherLocale: 'meta[property="og:locale:alternate"]',
  twitterTitle: 'meta[name="twitter:title"]',
  twitterDescription: 'meta[name="twitter:description"]',
};
function fixture(language: 'uk' | 'ru' = 'uk', query = '', withTags = true) {
  const tags = new Map<string, { value: string; getAttribute: (name: string) => string; setAttribute: (name: string, value: string) => void }>();
  const entry = metadata[language];
  const values = { description: entry.description, canonical: entry.url, ogTitle: entry.title, ogDescription: entry.description, ogUrl: entry.url, ogLocale: entry.locale, ogOtherLocale: metadata[language === 'uk' ? 'ru' : 'uk'].locale, twitterTitle: entry.title, twitterDescription: entry.description };
  if (withTags) for (const [name, selector] of Object.entries(selectors)) {
    const tag = { value: values[name as keyof typeof values], getAttribute: (_name: string) => tag.value, setAttribute: (_name: string, value: string) => { tag.value = value; } };
    tags.set(selector, tag);
  }
  const location = new URL(`https://joyrent.example/${query || (language === 'ru' ? '?lang=ru' : '')}`);
  const windowFixture = {
    location,
    history: { replaceState: (_state: unknown, _title: string, url: URL) => { windowFixture.location = new URL(url); } },
    addEventListener: () => {}, removeEventListener: () => {},
  };
  const documentFixture = { title: entry.title, documentElement: { lang: language }, querySelector: (selector: string) => tags.get(selector) ?? null };
  vi.stubGlobal('window', windowFixture);
  vi.stubGlobal('document', documentFixture);
  return { document: documentFixture, window: windowFixture, read: (name: keyof typeof selectors) => tags.get(selectors[name])?.value };
}
function render() {
  runtime.effects = [];
  const tree = LanguageProvider({ children: null }) as ReactElement<{ value: { setLanguage: (language: 'uk' | 'ru') => void } }>;
  runtime.effects.forEach(effect => effect());
  return tree.props.value;
}
function expectHead(head: ReturnType<typeof fixture>, language: 'uk' | 'ru') {
  const entry = metadata[language];
  expect(head.document.title).toBe(entry.title);
  expect(head.read('description')).toBe(entry.description);
  expect(head.read('canonical')).toBe(entry.url);
  expect(head.read('ogTitle')).toBe(entry.title);
  expect(head.read('ogDescription')).toBe(entry.description);
  expect(head.read('ogUrl')).toBe(entry.url);
  expect(head.read('ogLocale')).toBe(entry.locale);
  expect(head.read('ogOtherLocale')).toBe(metadata[language === 'uk' ? 'ru' : 'uk'].locale);
  expect(head.read('twitterTitle')).toBe(entry.title);
  expect(head.read('twitterDescription')).toBe(entry.description);
}
beforeEach(() => { runtime.state = undefined; runtime.ref = undefined; runtime.effects = []; runtime.boot.homeMetadata = metadata; });
afterEach(() => { vi.unstubAllGlobals(); });

describe('localized homepage head metadata', () => {
  it.each(['uk', 'ru'] as const)('retains supplied server copy on the first %s render', language => {
    const head = fixture(language);
    render();
    expectHead(head, language);
  });
  it('updates canonical and sharing metadata when language changes without removing address parameters or hash', () => {
    const head = fixture('uk', '?utm_source=instagram#games');
    render().setLanguage('ru');
    render();
    expect(head.read('canonical')).toBe(metadata.ru.url);
    expectHead(head, 'ru');
    expect(head.window.location.href).toBe('https://joyrent.example/?utm_source=instagram&lang=ru#games');
    render().setLanguage('uk');
    render();
    expectHead(head, 'uk');
    expect(head.window.location.href).toBe('https://joyrent.example/?utm_source=instagram#games');
  });
  it('preserves server metadata on the first render when boot metadata is absent or partial', () => {
    runtime.boot.homeMetadata = { ru: metadata.ru };
    const head = fixture('uk');
    render();
    expectHead(head, 'uk');
  });
  it('keeps preview fallback metadata in one language and excludes tracking parameters from canonical URLs', () => {
    delete runtime.boot.homeMetadata;
    const head = fixture('uk', '?utm_source=instagram#games');
    render().setLanguage('ru');
    render();
    expect(head.document.title).toBe('JOYRENT — аренда PlayStation 5 и PlayStation 4');
    expect(head.read('canonical')).toBe('https://joyrent.example/?lang=ru');
    expect(head.read('ogUrl')).toBe(head.read('canonical'));
    expect(head.read('ogTitle')).toBe(head.document.title);
    expect(head.read('twitterTitle')).toBe(head.document.title);
    expect(head.read('ogDescription')).toBe(head.read('description'));
    expect(head.read('twitterDescription')).toBe(head.read('description'));
    expect(head.read('ogLocale')).toBe('ru_UA');
    expect(head.read('ogOtherLocale')).toBe('uk_UA');
  });
  it('supports previews without SEO tags while still updating the document title', () => {
    const head = fixture('ru', '', false);
    expect(() => render()).not.toThrow();
    expect(head.document.title).toBe(metadata.ru.title);
  });
});
