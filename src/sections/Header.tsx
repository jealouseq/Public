import { useState } from 'react';
import { ArrowUpRight, List, X } from '@phosphor-icons/react';

const links = [['#rates', 'Консолі'], ['#games', 'Ігри'], ['#how-it-works', 'Як це працює'], ['#delivery', 'Доставка']];
export function Header() {
  const [open, setOpen] = useState(false);
  return <header className="site-header"><div className="shell header-inner">
    <a className="wordmark" href="#top" aria-label="JOYRENT — головна">JOYRENT<span className="brand-dot">.</span></a>
    <nav className="desktop-nav" aria-label="Головна навігація">{links.map(([href, label]) => <a key={href} href={href}>{label}</a>)}</nav>
    <a className="button button-outline header-cta" href="#booking">Обрати консоль <ArrowUpRight size={17} /></a>
    <button className="icon-button mobile-menu-toggle" aria-label={open ? 'Закрити меню' : 'Відкрити меню'} aria-expanded={open} aria-controls="mobile-navigation" onClick={() => setOpen(!open)}>{open ? <X size={24} /> : <List size={26} />}</button>
  </div>{open && <nav id="mobile-navigation" className="mobile-nav" aria-label="Мобільна навігація">{links.map(([href, label]) => <a key={href} href={href} onClick={() => setOpen(false)}>{label}<ArrowUpRight size={20} /></a>)}<a href="#booking" onClick={() => setOpen(false)}>Обрати дати<ArrowUpRight size={20} /></a></nav>}</header>;
}
