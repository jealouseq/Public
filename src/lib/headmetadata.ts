import type { Language } from './i18n';

export type HomeMetadataEntry = { title: string; description: string; url: string; locale: string };
export type HomeMetadata = Partial<Record<Language, HomeMetadataEntry>>;
type HeadDocument = Pick<Document, 'title' | 'querySelector'>;

/** Keep the existing server-rendered tags aligned with the chosen language. */
export function applyHeadMetadata(head: HeadDocument, entry: HomeMetadataEntry, alternateLocale: string): void {
  head.title = entry.title;
  for (const [selector, attribute, value] of [
    ['meta[name="description"]', 'content', entry.description],
    ['link[rel="canonical"]', 'href', entry.url],
    ['meta[property="og:title"]', 'content', entry.title],
    ['meta[property="og:description"]', 'content', entry.description],
    ['meta[property="og:url"]', 'content', entry.url],
    ['meta[property="og:locale"]', 'content', entry.locale],
    ['meta[property="og:locale:alternate"]', 'content', alternateLocale],
    ['meta[name="twitter:title"]', 'content', entry.title],
    ['meta[name="twitter:description"]', 'content', entry.description],
  ]) head.querySelector(selector)?.setAttribute(attribute, value);
}

/** Static previews and older boot configurations retain the existing basic copy. */
export function fallbackHeadMetadata(head: HeadDocument, language: Language, address: string): HomeMetadataEntry {
  const alternate = head.querySelector(`link[rel="alternate"][hreflang="${language}"]`)?.getAttribute('href');
  const url = new URL(alternate || address, address);
  url.hash = '';
  if (!alternate) {
    url.search = '';
    if (language === 'ru') url.searchParams.set('lang', 'ru');
  }
  return language === 'ru'
    ? { title: 'JOYRENT — аренда PlayStation 5 и PlayStation 4', description: 'JOYRENT — аренда PlayStation 5 и PlayStation 4. Выбирай консоль, даты и любимые игры.', url: url.href, locale: 'ru_UA' }
    : { title: 'JOYRENT — оренда PlayStation 5 та PlayStation 4', description: 'JOYRENT — оренда PlayStation 5 та PlayStation 4. Обирай консоль, дати та улюблені ігри.', url: url.href, locale: 'uk_UA' };
}
