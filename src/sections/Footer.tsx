import { ArrowUpRight, Phone, TelegramLogo, EnvelopeSimple } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';
import { money, dayLabel, type ConsoleId } from '../lib/rental';
import { faqUrl, type StoreSettings } from '../lib/api';
import { useI18n } from '../lib/i18n';
import '../contact-refinement.css';

function displayPhone(phone: string) {
  const digits = phone.replace(/\D/g, '');
  if (/^380\d{9}$/.test(digits)) {
    const local = `0${digits.slice(3)}`;
    const short = `${local.slice(0, 3)} ${local.slice(3, 6)} ${local.slice(6, 8)} ${local.slice(8)}`;
    return { full: `+380 ${digits.slice(3, 5)} ${digits.slice(5, 8)} ${digits.slice(8, 10)} ${digits.slice(10)}`, short };
  }
  return { full: phone, short: phone };
}

export function Footer({ settings, price, consoleId, days, onLegal }: { settings: StoreSettings; price: number | null; consoleId: ConsoleId; days: number; onLegal: (page: 'privacy' | 'terms') => void }) {
  const [bookingVisible, setBookingVisible] = useState(false);
  const [heroVisible, setHeroVisible] = useState(true);
  const { t, language } = useI18n();
  const phone = displayPhone(settings.phone || '');
  useEffect(() => { const booking = document.getElementById('booking'); if (!booking) return; const observer = new IntersectionObserver(([entry]) => setBookingVisible(entry.isIntersecting)); observer.observe(booking); return () => observer.disconnect(); }, []);
  useEffect(() => { const hero = document.getElementById('top'); if (!hero) return; const observer = new IntersectionObserver(([entry]) => setHeroVisible(entry.isIntersecting)); observer.observe(hero); return () => observer.disconnect(); }, []);
  return <>
    <footer className="site-footer" id="contact"><div className="shell"><div className="footer-top"><a href="#top" className="wordmark" aria-label={t('JOYRENT — на початок', 'JOYRENT — в начало')}>JOYRENT<span className="brand-dot">.</span></a><div className="footer-contacts">{settings.phone && <a className="footer-contact footer-phone" href={`tel:${settings.phone.replace(/[^+\d]/g, '')}`} aria-label={`${t('Зателефонувати', 'Позвонить')}: ${phone.full}`} title={phone.full}><Phone size={18} weight="light" /><span className="phone-full">{phone.full}</span><span className="phone-short" aria-hidden="true">{phone.short}</span></a>}{settings.telegram && <a className="footer-contact footer-telegram" href={settings.telegram} target="_blank" rel="noreferrer" aria-label={t('Написати JOYRENT у Telegram', 'Написать JOYRENT в Telegram')} title="Telegram · JOYRENT"><TelegramLogo size={20} weight="light" /><span>Telegram</span></a>}{settings.email && <a className="footer-contact footer-email" href={`mailto:${settings.email}`}><EnvelopeSimple size={18} weight="light" /><span>{settings.email}</span></a>}{!settings.phone && !settings.email && !settings.telegram && <a className="footer-contact" href="#booking">{t('Забронювати', 'Забронировать')} <ArrowUpRight size={15} /></a>}</div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} JOYRENT</span><div><button onClick={() => onLegal('terms')}>{t('Умови оренди', 'Условия аренды')}</button><button onClick={() => onLegal('privacy')}>{t('Конфіденційність', 'Конфиденциальность')}</button><a href={faqUrl(language)}>FAQ</a></div><span className="footer-note">{t('З турботою про твій вечір.', 'С заботой о твоём вечере.')}</span></div></div></footer>
    {!bookingVisible && !heroVisible && <div className="mobile-rental-bar"><span>{consoleId.toUpperCase()} <strong>{price === null ? t('Тариф недоступний', 'Тариф недоступен') : `${money(price)} грн / ${dayLabel(days, language)}`}</strong></span><a href="#booking" className="button button-light">{t('Орендувати', 'Арендовать')} <ArrowUpRight size={17} /></a></div>}</>;
}
