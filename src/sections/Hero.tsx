import { useMotionPreference } from '../lib/motion';
import { ArrowUpRight } from '@phosphor-icons/react';
import { motion } from 'framer-motion';
import { money } from '../lib/rental';
import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function Hero({ price, onChoosePS5 }: { price: number; onChoosePS5: () => void }) {
  const reduced = useMotionPreference();
  const { t } = useI18n();
  const lines = [t('Твій вечір.', 'Твой вечер.'), t('Твоя PlayStation.', 'Твоя PlayStation.')];
  const photo = imageUrl('ps5-hero-cutout');
  return <section className="hero" id="top"><div className="shell hero-inner">
    <div className="hero-content">
      <p className="eyebrow hero-eyebrow">{t('Оренда PlayStation', 'Аренда PlayStation')}</p>
      <h1>{lines.map((line, index) => <span className="hero-line" key={index}><motion.span initial={reduced ? false : { y: '105%', opacity: 0 }} animate={{ y: 0, opacity: 1 }} transition={{ duration: reduced ? 0 : 0.9, delay: reduced ? 0 : index * 0.12, ease: [0.22, 1, 0.36, 1] }}>{line}</motion.span></span>)}</h1>
      <p className="hero-description">{t('PS5 або PS4 на день, вихідні чи довше. Обери консоль і дати — решту узгодимо з тобою.', 'PS5 или PS4 на день, выходные или дольше. Выбери консоль и даты — остальное согласуем с тобой.')}</p>
      <div className="hero-actions"><a href="#rates" onClick={onChoosePS5} className="button button-light">{t('Обрати PS5', 'Выбрать PS5')} <ArrowUpRight size={21} /></a><a href="#rates" className="text-link">{t('Тарифи PS5 / PS4', 'Тарифы PS5 / PS4')}</a></div>
      <p className="hero-price">{t('Від', 'От')} <strong>{money(price)}</strong><span>грн / 1 {t('день', 'день')}</span></p>
    </div>
    <div className="hero-scene" aria-hidden="true"><img className="hero-photo" src={photo} alt="" width={1024} height={1536} fetchPriority="high" /><motion.img className="hero-light-pass" src={photo} alt="" width={1024} height={1536} initial={reduced ? false : { opacity: 0.03 }} animate={{ opacity: reduced ? 0.07 : [0.03, 0.15, 0.03] }} transition={{ duration: reduced ? 0 : 12, ease: 'easeInOut', repeat: reduced ? 0 : Infinity }} /></div>
  </div></section>;
}
