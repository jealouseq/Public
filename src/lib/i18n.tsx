import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { boot } from './api';
import { applyHeadMetadata, fallbackHeadMetadata } from './headmetadata';

export type Language = 'uk' | 'ru';
const languageFromUrl = (): Language => new URLSearchParams(window.location.search).get('lang') === 'ru' ? 'ru' : 'uk';
const LanguageContext = createContext({ language: 'uk' as Language, setLanguage: (_value: Language) => {}, t: (uk: string, _ru: string) => uk });
export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>(languageFromUrl);
  const previousLanguage = useRef<Language | null>(null);
  useEffect(() => {
    document.documentElement.lang = language;
    const url = new URL(window.location.href);
    if (language === 'ru') url.searchParams.set('lang', 'ru'); else url.searchParams.delete('lang');
    window.history.replaceState(null, '', url);
    const otherLanguage = language === 'ru' ? 'uk' : 'ru';
    const metadata = boot.homeMetadata?.[language];
    if (metadata) {
      applyHeadMetadata(document, metadata, boot.homeMetadata?.[otherLanguage]?.locale ?? (otherLanguage === 'ru' ? 'ru_UA' : 'uk_UA'));
    } else if (previousLanguage.current !== null && previousLanguage.current !== language) {
      applyHeadMetadata(document, fallbackHeadMetadata(document, language, url.href), otherLanguage === 'ru' ? 'ru_UA' : 'uk_UA');
    }
    previousLanguage.current = language;
  }, [language]);
  useEffect(() => { const onHistory = () => setLanguage(languageFromUrl()); window.addEventListener('popstate', onHistory); return () => window.removeEventListener('popstate', onHistory); }, []);
  return <LanguageContext.Provider value={{ language, setLanguage, t: (uk, ru) => language === 'ru' ? ru : uk }}>{children}</LanguageContext.Provider>;
}
export const useI18n = () => useContext(LanguageContext);
