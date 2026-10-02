import { ArrowUpRight } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';
import { Reveal } from '../../components/ui/reveal';
import { money, dayLabel, type ConsoleId } from '../lib/rental';
import type { StoreSettings } from '../lib/api';

export function Footer({ settings, price, consoleId, minimumDays, onLegal }: { settings: StoreSettings; price: number; consoleId: ConsoleId; minimumDays: number; onLegal: (page: 'privacy' | 'terms') => void }) {
  const [bookingVisible, setBookingVisible] = useState(false);
  useEffect(() => {
    const booking = document.getElementById('booking');
    if (!booking) return;
    const observer = new IntersectionObserver(([entry]) => setBookingVisible(entry.isIntersecting));
    observer.observe(booking);
    return () => observer.disconnect();
  }, []);
  return <><section className="final-cta"><div className="shell"><Reveal><p className="eyebrow">НАСТУПНИЙ РІВЕНЬ — ТВІЙ</p><h2>Готовий<br />грати?</h2><a href="#booking" className="button button-light">Обрати консоль і дати <ArrowUpRight size={23} /></a></Reveal><p className="cta-side">Вечір удома.<br />Пригода де завгодно.</p></div></section><footer className="site-footer"><div className="shell"><div className="footer-top"><a href="#top" className="wordmark">JOYRENT<span className="brand-dot">.</span></a><p>Хороші вечори починаються тут.</p><div className="footer-contacts">{settings.phone && <a href={`tel:${settings.phone.replace(/[^+\d]/g, '')}`}>{settings.phone}</a>}{settings.telegram && <a href={settings.telegram} target="_blank" rel="noreferrer">Telegram <ArrowUpRight size={15} /></a>}{settings.email && <a href={`mailto:${settings.email}`}>{settings.email}</a>}{!settings.phone && !settings.email && !settings.telegram && <a href="#booking">Залишити заявку <ArrowUpRight size={15} /></a>}</div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} JOYRENT</span><div><button onClick={() => onLegal('terms')}>Умови оренди</button><button onClick={() => onLegal('privacy')}>Конфіденційність</button><a href="#faq">FAQ</a></div><span className="footer-note">PlayStation — торгова марка Sony.</span></div></div></footer>{!bookingVisible && <div className="mobile-rental-bar"><span>{consoleId.toUpperCase()} <strong>від {money(price)} грн / {dayLabel(minimumDays)}</strong></span><a href="#booking" className="button button-light">Орендувати <ArrowUpRight size={17} /></a></div>}</>;
}
