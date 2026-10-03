import { CaretRight } from '@phosphor-icons/react';
import { money, type ConsoleId } from '../lib/rental';
import { boot } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { BlurredStagger } from '../../components/ui/blurred-stagger-text';
import media from '../../wordpress/joyrent/assets/images/hero-media.json';
import '../contact-refinement.css';

const asset = (file: string) => `${boot.assetBase}/images/${file}`;
const sources = (items: { file: string; width: number }[]) => items.map(item => `${asset(item.file)} ${item.width}w`).join(', ');

export function Hero({ price, minimumDays = 1, consoleId = 'ps5', onChoosePS5 }: { price: number | null; minimumDays?: number; consoleId?: ConsoleId; onChoosePS5: () => void }) {
  const { t } = useI18n();
  const lines = [t('Твій вечір.', 'Твой вечер.'), t('Твоя PlayStation.', 'Твоя PlayStation.')];
  return <section className="hero" id="top"><div className="shell hero-inner">
    <div className="hero-content">
      <p className="eyebrow hero-eyebrow">{t('Оренда PlayStation', 'Аренда PlayStation')}</p>
      <h1 aria-label={lines.join(' ')}>{lines.map((line, index) => <BlurredStagger key={line} text={line} delay={index * 0.16} />)}</h1>
      <p className="hero-description">{t('PS5 або PS4 на день, вихідні чи довше. Обери консоль і дати — решту узгодимо з тобою.', 'PS5 или PS4 на день, выходные или дольше. Выбери консоль и даты — остальное согласуем с тобой.')}</p>
      <div className="hero-actions"><a href="#rates" onClick={price === null ? undefined : onChoosePS5} className="button button-light hero-primary">{price === null ? t('Перевірити тарифи', 'Проверить тарифы') : `${t('Обрати', 'Выбрать')} ${consoleId.toUpperCase()}`} <span className="hero-primary-direction" aria-hidden="true"><CaretRight size={18} weight="light" /></span></a><a href="#rates" className="text-link">{t('Тарифи PS5 / PS4', 'Тарифы PS5 / PS4')}</a></div>
      <p className="hero-price">{price === null ? t('Тарифи тимчасово недоступні', 'Тарифы временно недоступны') : <>{t('від', 'от')} <strong>{money(price / minimumDays)}</strong><span> грн/день</span></>}</p>
    </div>
    <figure className="hero-visual" aria-hidden="true">
      <picture>
        <source media={media.mobileMedia} srcSet={sources(media.mobile.sources)} sizes={media.mobile.sizes} width={media.mobile.width} height={media.mobile.height} />
        <img className="hero-photo" src={asset(media.desktop.fallback)} srcSet={sources(media.desktop.sources)} sizes={media.desktop.sizes} alt="" width={media.desktop.width} height={media.desktop.height} loading="eager" fetchPriority="high" decoding="async" />
      </picture>
    </figure>
  </div></section>;
}
