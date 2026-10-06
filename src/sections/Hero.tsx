import { useEffect, useState, type AnimationEvent, type CSSProperties } from 'react';
import { CaretRight } from '@phosphor-icons/react';
import { money, type ConsoleId } from '../lib/rental';
import { boot } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { useVisibleMotion } from '../lib/use-visible-motion';
import { BlurredStagger } from '../../components/ui/blurred-stagger-text';
import media from '../../wordpress/joyrent/assets/images/hero-media.json';
import '../contact-refinement.css';
import '../hero-mobile-refinement.css';
import '../hero-composition.css';

const asset = (file: string) => `${boot.assetBase}/images/${file}`;
const sources = (items: { file: string; width: number }[]) => items.map(item => `${asset(item.file)} ${item.width}w`).join(', ');
type HeroImage = { width: number; height: number; fallback: string; sizes: string; sources: { file: string; width: number }[] };
type HeroMedia = { mobileMedia: string; desktop: HeroImage; mobile: HeroImage; light?: { desktop: HeroImage; mobile: HeroImage } };
const heroMedia: HeroMedia = media;
const sceneRatios = {
  '--hero-scene-ratio': `${heroMedia.desktop.width} / ${heroMedia.desktop.height}`,
  '--hero-mobile-scene-ratio': `${heroMedia.mobile.width} / ${heroMedia.mobile.height}`,
} as CSSProperties;

export function Hero({ price, minimumDays = 1, consoleId = 'ps5', onChoosePS5 }: { price: number | null; minimumDays?: number; consoleId?: ConsoleId; onChoosePS5: () => void }) {
  const { t } = useI18n();
  const { ref, active, reduced } = useVisibleMotion();
  const [imageReady, setImageReady] = useState(false);
  const [revealed, setRevealed] = useState(false);
  const [saveData] = useState(() => Boolean((navigator as Navigator & { connection?: { saveData?: boolean } }).connection?.saveData));
  useEffect(() => { if (reduced) setRevealed(true); }, [reduced]);
  function finishReveal(event: AnimationEvent<HTMLDivElement>) {
    if (event.target === event.currentTarget && event.animationName === 'joyrent-hero-scene-in') setRevealed(true);
  }
  const lines = [t('Твій вечір.', 'Твой вечер.'), t('Твоя PlayStation.', 'Твоя PlayStation.')];
  return <section className="hero" id="top"><div className="shell hero-inner">
    <div className="hero-content">
      <p className="eyebrow hero-eyebrow">{t('Оренда PlayStation', 'Аренда PlayStation')}</p>
      <h1 aria-label={lines.join(' ')}>{lines.map((line, index) => <BlurredStagger key={line} text={line} delay={index * 0.16} />)}</h1>
      <p className="hero-description"><span className="hero-description-part">{t('PS5 від 1 дня, PS4 — від 3 днів.', 'PS5 от 1 дня, PS4 — от 3 дней.')}</span>{' '}<span className="hero-description-part">{t('Обери консоль і дати — решту узгодимо з тобою.', 'Выбери консоль и даты — остальное согласуем с тобой.')}</span></p>
      <div className="hero-actions"><a href="#rates" onClick={price === null ? undefined : onChoosePS5} className="button button-light hero-primary">{price === null ? t('Перевірити тарифи', 'Проверить тарифы') : `${t('Обрати', 'Выбрать')} ${consoleId.toUpperCase()}`} <span className="hero-primary-direction" aria-hidden="true"><CaretRight size={18} weight="light" /></span></a><a href="#rates" className="text-link">{t('Тарифи PS5 / PS4', 'Тарифы PS5 / PS4')}</a></div>
      <p className="hero-price">{price === null ? t('Тарифи тимчасово недоступні', 'Тарифы временно недоступны') : <>{t('від', 'от')} <strong>{money(price / minimumDays)}</strong><span> грн/день</span></>}</p>
    </div>
    <figure className="hero-visual" aria-hidden="true" style={sceneRatios}>
      <div ref={ref} className="hero-product-stage" data-motion={active ? 'running' : 'paused'}>
        <div className="hero-product-scene" data-ready={imageReady} data-reveal={revealed || reduced ? 'settled' : 'pending'} onAnimationEnd={finishReveal}>
          <picture className="hero-product-picture">
            <source media={heroMedia.mobileMedia} srcSet={sources(heroMedia.mobile.sources)} sizes={heroMedia.mobile.sizes} width={heroMedia.mobile.width} height={heroMedia.mobile.height} />
            <img className="hero-photo" src={asset(heroMedia.desktop.fallback)} srcSet={sources(heroMedia.desktop.sources)} sizes={heroMedia.desktop.sizes} alt="" width={heroMedia.desktop.width} height={heroMedia.desktop.height} loading="eager" fetchPriority="high" decoding="async" onLoad={() => setImageReady(true)} />
          </picture>
          {heroMedia.light && !saveData && <picture className="hero-product-light">
            <source media={heroMedia.mobileMedia} srcSet={sources(heroMedia.light.mobile.sources)} sizes={heroMedia.light.mobile.sizes} width={heroMedia.light.mobile.width} height={heroMedia.light.mobile.height} />
            <img className="hero-light-photo" src={asset(heroMedia.light.desktop.fallback)} srcSet={sources(heroMedia.light.desktop.sources)} sizes={heroMedia.light.desktop.sizes} alt="" width={heroMedia.light.desktop.width} height={heroMedia.light.desktop.height} loading="eager" fetchPriority="low" decoding="async" />
          </picture>}
        </div>
      </div>
    </figure>
  </div></section>;
}
