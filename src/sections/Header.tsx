import { useState } from 'react';
import { ArrowUpRight, List, X } from '@phosphor-icons/react';
import { useI18n } from '../lib/i18n';

export function Header() {
  const [open, setOpen] = useState(false);
  const { language, setLanguage, t } = useI18n();
  const links = [['#rates', t('Консолі', 'Консоли')], ['#games', t('Ігри', 'Игры')], ['#how-it-works', t('Умови оренди', 'Условия аренды')]];
  return <header className="site-header"><div className="shell header-inner">
    <a className="wordmark" href="#top" aria-label={t('JOYRENT — головна', 'JOYRENT — главная')}>JOYRENT<span className="brand-dot">.</span></a>
    <nav className="desktop-nav" aria-label={t('Головна навігація', 'Главная навигация')}>{links.map(([href, label]) => <a key={href} href={href}>{label}</a>)}</nav>
    <div className="header-actions"><div className="language-switch" role="group" aria-label={t('Мова сайту', 'Язык сайта')}>{(['uk', 'ru'] as const).map(value => <button type="button" key={value} lang={value} aria-pressed={language === value} aria-label={value === 'uk' ? 'Українська' : 'Русский'} onClick={() => setLanguage(value)}>{value === 'uk' ? 'UA' : 'RU'}</button>)}</div>
    <a className="button button-outline header-cta" href="#booking">{t('Обрати дати', 'Выбрать даты')} <ArrowUpRight size={17} /></a>
    <button className="icon-button mobile-menu-toggle" aria-label={open ? t('Закрити меню', 'Закрыть меню') : t('Відкрити меню', 'Открыть меню')} aria-expanded={open} aria-controls="mobile-navigation" onClick={() => setOpen(!open)}>{open ? <X size={24} /> : <List size={26} />}</button></div>
  </div>{open && <nav id="mobile-navigation" className="mobile-nav" aria-label={t('Мобільна навігація', 'Мобильная навигация')}>{[...links, ['#booking', t('Обрати дати', 'Выбрать даты')], ['#faq', t('Питання про оренду', 'Вопросы об аренде')]].map(([href, label]) => <a key={href} href={href} onClick={() => setOpen(false)}>{label}<ArrowUpRight size={20} /></a>)}</nav>}</header>;
}
