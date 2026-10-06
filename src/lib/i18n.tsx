import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type Language = 'uk' | 'ru';
const languageFromUrl = (): Language => new URLSearchParams(window.location.search).get('lang') === 'ru' ? 'ru' : 'uk';
const LanguageContext = createContext({ language: 'uk' as Language, setLanguage: (_value: Language) => {}, t: (uk: string, _ru: string) => uk });
export function LanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<Language>(languageFromUrl);
  useEffect(() => {
    document.documentElement.lang = language;
    document.title = language === 'ru' ? 'JOYRENT — аренда PlayStation 5 и PlayStation 4' : 'JOYRENT — оренда PlayStation 5 та PlayStation 4';
    document.querySelector('meta[name="description"]')?.setAttribute('content', language === 'ru' ? 'JOYRENT — аренда PlayStation 5 и PlayStation 4. Выбирай консоль, даты и любимые игры.' : 'JOYRENT — оренда PlayStation 5 та PlayStation 4. Обирай консоль, дати та улюблені ігри.');
    const url = new URL(window.location.href);
    if (language === 'ru') url.searchParams.set('lang', 'ru'); else url.searchParams.delete('lang');
    window.history.replaceState(null, '', url);
  }, [language]);
  useEffect(() => { const onHistory = () => setLanguage(languageFromUrl()); window.addEventListener('popstate', onHistory); return () => window.removeEventListener('popstate', onHistory); }, []);
  return <LanguageContext.Provider value={{ language, setLanguage, t: (uk, ru) => language === 'ru' ? ru : uk }}>{children}</LanguageContext.Provider>;
}
export const useI18n = () => useContext(LanguageContext);
