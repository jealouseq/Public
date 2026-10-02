import { ArrowUpRight } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';
import { Reveal } from '../../components/ui/reveal';
import { money, dayLabel, type ConsoleId } from '../lib/rental';
import type { StoreSettings } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function Footer({ settings, price, consoleId, minimumDays, onLegal }: { settings: StoreSettings; price: number; consoleId: ConsoleId; minimumDays: number; onLegal: (page: 'privacy' | 'terms') => void }) {
  const [bookingVisible, setBookingVisible] = useState(false);
  const { t, language } = useI18n();
  useEffect(() => { const booking = document.getElementById('booking'); if (!booking) return; const observer = new IntersectionObserver(([entry]) => setBookingVisible(entry.isIntersecting)); observer.observe(booking); return () => observer.disconnect(); }, []);
  return <><section className="final-cta"><div className="shell"><Reveal className="cta-panel"><div><h2>{t('Вечір починається з вибору.', 'Вечер начинается с выбора.')}</h2><p>{t('Знайди свою консоль і зручні дати.', 'Найди свою консоль и удобные даты.')}</p></div><a href="#booking" className="button button-light">{t('Обрати дати', 'Выбрать даты')} <ArrowUpRight size={22} /></a></Reveal></div></section>
    <footer className="site-footer"><div className="shell"><div className="footer-top"><a href="#top" className="wordmark">JOYRENT<span className="brand-dot">.</span></a><div className="footer-contacts">{settings.phone && <a href={`tel:${settings.phone.replace(/[^+\d]/g, '')}`}>{settings.phone}</a>}{settings.telegram && <a href={settings.telegram} target="_blank" rel="noreferrer">Telegram <ArrowUpRight size={15} /></a>}{settings.email && <a href={`mailto:${settings.email}`}>{settings.email}</a>}{!settings.phone && !settings.email && !settings.telegram && <a href="#booking">{t('Залишити заявку', 'Оставить заявку')} <ArrowUpRight size={15} /></a>}</div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} JOYRENT</span><div><button onClick={() => onLegal('terms')}>{t('Умови оренди', 'Условия аренды')}</button><button onClick={() => onLegal('privacy')}>{t('Конфіденційність', 'Конфиденциальность')}</button><a href="#faq">FAQ</a></div><span className="footer-note">{t('PlayStation — торгова марка Sony.', 'PlayStation — торговая марка Sony.')}</span></div></div></footer>
    {!bookingVisible && <div className="mobile-rental-bar"><span>{consoleId.toUpperCase()} <strong>{t('від', 'от')} {money(price)} грн / {dayLabel(minimumDays, language)}</strong></span><a href="#booking" className="button button-light">{t('Орендувати', 'Арендовать')} <ArrowUpRight size={17} /></a></div>}</>;
}
