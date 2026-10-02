import { useEffect, useRef, useState } from 'react';
import { ArrowUpRight, List, X } from '@phosphor-icons/react';
import { faqUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function Header() {
  const [open, setOpen] = useState(false);
  const header = useRef<HTMLElement>(null);
  const toggle = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (!open) return;
    header.current?.querySelector<HTMLAnchorElement>('.mobile-nav a')?.focus();
    const escape = (event: KeyboardEvent) => { if (event.key === 'Escape') { setOpen(false); toggle.current?.focus(); } };
    const outside = (event: PointerEvent) => { if (!header.current?.contains(event.target as Node)) setOpen(false); };
    const desktop = window.matchMedia('(min-width: 981px)');
    const resize = () => { if (desktop.matches) setOpen(false); };
    document.addEventListener('keydown', escape);
    document.addEventListener('pointerdown', outside);
    desktop.addEventListener('change', resize);
    return () => { document.removeEventListener('keydown', escape); document.removeEventListener('pointerdown', outside); desktop.removeEventListener('change', resize); };
  }, [open]);
  const { language, setLanguage, t } = useI18n();
  const links = [['#rates', t('Консолі', 'Консоли')], ['#games', t('Ігри', 'Игры')], ['#how-it-works', t('Як орендувати', 'Как арендовать')], [faqUrl(language), 'FAQ']];
  return <header className="site-header" ref={header}><div className="shell header-inner">
    <a className="wordmark" href="#top" onClick={() => setOpen(false)} aria-label={t('JOYRENT — головна', 'JOYRENT — главная')}>JOYRENT<span className="brand-dot">.</span></a>
    <nav className="desktop-nav" aria-label={t('Головна навігація', 'Главная навигация')}>{links.map(([href, label]) => <a key={href} href={href}>{label}</a>)}</nav>
    <div className="header-actions"><div className="language-switch" role="group" aria-label={t('Мова сайту', 'Язык сайта')}>{(['uk', 'ru'] as const).map(value => <button type="button" key={value} lang={value} aria-pressed={language === value} aria-label={value === 'uk' ? 'Українська' : 'Русский'} onClick={() => { setLanguage(value); setOpen(false); }}>{value === 'uk' ? 'UA' : 'RU'}</button>)}</div>
    <a className="button button-outline header-cta" href="#booking">{t('Обрати дати', 'Выбрать даты')} <ArrowUpRight size={17} /></a>
    <button className="icon-button mobile-menu-toggle" ref={toggle} aria-label={open ? t('Закрити меню', 'Закрыть меню') : t('Відкрити меню', 'Открыть меню')} aria-expanded={open} aria-controls={open ? "mobile-navigation" : undefined} onClick={() => setOpen(!open)}>{open ? <X size={24} /> : <List size={26} />}</button></div>
  </div>{open && <nav id="mobile-navigation" className="mobile-nav" aria-label={t('Мобільна навігація', 'Мобильная навигация')}>{[...links, ['#booking', t('Обрати дати', 'Выбрать даты')]].map(([href, label]) => <a key={href} href={href} onClick={() => setOpen(false)}>{label}<ArrowUpRight size={20} /></a>)}</nav>}</header>;
}
