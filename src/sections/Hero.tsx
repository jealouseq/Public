import { useMotionPreference } from '../lib/motion';
import { ArrowUpRight } from '@phosphor-icons/react';
import { motion } from 'framer-motion';
import { money } from '../lib/rental';
import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { BlurredStagger } from '../../components/ui/blurred-stagger-text';

export function Hero({ price, onChoosePS5 }: { price: number; onChoosePS5: () => void }) {
  const reduced = useMotionPreference();
  const { t } = useI18n();
  const lines = [t('Твій вечір.', 'Твой вечер.'), t('Твоя PlayStation.', 'Твоя PlayStation.')];
  const photo = imageUrl('ps5-hero-desktop');
  const mobilePhoto = imageUrl('ps5-hero-mobile');
  return <section className="hero" id="top"><div className="shell hero-inner">
    <div className="hero-content">
      <p className="eyebrow hero-eyebrow">{t('Оренда PlayStation', 'Аренда PlayStation')}</p>
      <h1 aria-label={lines.join(' ')}>{lines.map((line, index) => <BlurredStagger key={line} text={line} delay={index * 0.16} />)}</h1>
      <p className="hero-description">{t('PS5 або PS4 на день, вихідні чи довше. Обери консоль і дати — решту узгодимо з тобою.', 'PS5 или PS4 на день, выходные или дольше. Выбери консоль и даты — остальное согласуем с тобой.')}</p>
      <div className="hero-actions"><a href="#rates" onClick={onChoosePS5} className="button button-light">{t('Обрати PS5', 'Выбрать PS5')} <ArrowUpRight size={21} /></a><a href="#rates" className="text-link">{t('Тарифи PS5 / PS4', 'Тарифы PS5 / PS4')}</a></div>
      <p className="hero-price">{t('Від', 'От')} <strong>{money(price)}</strong><span>грн / 1 {t('день', 'день')}</span></p>
    </div>
  </div><div className="hero-scene" aria-hidden="true">
    <picture><source media="(max-width: 700px)" srcSet={mobilePhoto} width={1254} height={1254} /><img className="hero-photo" src={photo} alt="" width={1672} height={941} fetchPriority="high" /></picture>
    <motion.picture className="hero-light-pass" initial={reduced ? false : { opacity: 0.03 }} animate={{ opacity: reduced ? 0.07 : [0.03, 0.15, 0.03] }} transition={{ duration: reduced ? 0 : 12, ease: 'easeInOut', repeat: reduced ? 0 : Infinity }}>
      <source media="(max-width: 700px)" srcSet={mobilePhoto} width={1254} height={1254} /><img src={photo} alt="" width={1672} height={941} />
    </motion.picture>
  </div></section>;
}
