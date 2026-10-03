import { ArrowUpRight } from '@phosphor-icons/react';
import { money } from '../lib/rental';
import { boot } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { BlurredStagger } from '../../components/ui/blurred-stagger-text';
import media from '../../wordpress/joyrent/assets/images/hero-media.json';

const asset = (file: string) => `${boot.assetBase}/images/${file}`;
const sources = (items: { file: string; width: number }[]) => items.map(item => `${asset(item.file)} ${item.width}w`).join(', ');

export function Hero({ price, onChoosePS5 }: { price: number; onChoosePS5: () => void }) {
  const { t } = useI18n();
  const lines = [t('Твій вечір.', 'Твой вечер.'), t('Твоя PlayStation.', 'Твоя PlayStation.')];
  return <section className="hero" id="top"><div className="shell hero-inner">
    <div className="hero-content">
      <p className="eyebrow hero-eyebrow">{t('Оренда PlayStation', 'Аренда PlayStation')}</p>
      <h1 aria-label={lines.join(' ')}>{lines.map((line, index) => <BlurredStagger key={line} text={line} delay={index * 0.16} />)}</h1>
      <p className="hero-description">{t('PS5 або PS4 на день, вихідні чи довше. Обери консоль і дати — решту узгодимо з тобою.', 'PS5 или PS4 на день, выходные или дольше. Выбери консоль и даты — остальное согласуем с тобой.')}</p>
      <div className="hero-actions"><a href="#rates" onClick={onChoosePS5} className="button button-light">{t('Обрати PS5', 'Выбрать PS5')} <ArrowUpRight size={21} /></a><a href="#rates" className="text-link">{t('Тарифи PS5 / PS4', 'Тарифы PS5 / PS4')}</a></div>
      <p className="hero-price">{t('Від', 'От')} <strong>{money(price)}</strong><span>грн / 1 {t('день', 'день')}</span></p>
    </div>
    <figure className="hero-visual" aria-hidden="true">
      <picture>
        <source media={media.mobileMedia} srcSet={sources(media.mobile.sources)} sizes={media.mobile.sizes} width={media.mobile.width} height={media.mobile.height} />
        <img className="hero-photo" src={asset(media.desktop.fallback)} srcSet={sources(media.desktop.sources)} sizes={media.desktop.sizes} alt="" width={media.desktop.width} height={media.desktop.height} loading="eager" fetchPriority="high" decoding="async" />
      </picture>
    </figure>
  </div></section>;
}
