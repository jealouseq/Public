import { Check } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { useNearbyMedia } from '../lib/use-nearby-media';
import { useVisibleMotion } from '../lib/use-visible-motion';
import { KitGameBox } from './KitGameBox';
import '../rental-kit.css';

export function RentalKit({ secondFree = false }: { secondFree?: boolean }) {
  const { t } = useI18n();
  const { ref, active } = useVisibleMotion();
  const nearby = useNearbyMedia(ref);
  return <section className="section kit-section story-kit" id="kit"><div className="shell kit-layout">
    <Reveal className="kit-copy">
      <p className="eyebrow">{t('КОМПЛЕКТ JOYRENT', 'КОМПЛЕКТ JOYRENT')}</p>
      <h2>{t('Усе готово до гри.', 'Всё готово к игре.')}</h2>
      <ul className="kit-checklist">
        <li><Check size={18} aria-hidden="true" /><span><strong>PS5 {t('або', 'или')} PS4</strong><span>{secondFree ? t('Два геймпади без доплати', 'Два геймпада без доплаты') : t('Один або два геймпади на вибір', 'Один или два геймпада на выбор')}</span></span></li>
        <li><Check size={18} aria-hidden="true" /><span><strong>{t('Усе для підключення', 'Всё для подключения')}</strong><span>{t('HDMI, живлення, заряджання та коротка інструкція', 'HDMI, питание, зарядка и краткая инструкция')}</span></span></li>
      </ul>
      <p className="kit-note">{t('Кожен комплект перевіряємо перед видачею.', 'Каждый комплект проверяем перед выдачей.')}</p>
    </Reveal>
    <div className="kit-visual">
      <div ref={ref} className="kit-product-stage" data-motion={active ? 'running' : 'paused'}>
        {nearby && <>
          <img className="kit-studio-floor" src={imageUrl('kit-studio-floor-560')} srcSet={`${imageUrl('kit-studio-floor-560')} 560w, ${imageUrl('kit-studio-floor-1120')} 1120w`} sizes="(max-width: 700px) 100vw, (max-width: 1100px) 60vw, 920px" width={1774} height={887} alt="" loading="lazy" decoding="async" />
          <div className="kit-item kit-card-plus"><div className="kit-element-motion"><img className="kit-subscription-image" src={imageUrl('kit-ps-plus-card-320')} srcSet={`${imageUrl('kit-ps-plus-card-320')} 320w, ${imageUrl('kit-ps-plus-card-640')} 640w`} sizes="(max-width: 700px) 17vw, (max-width: 1100px) 11vw, 140px" width={1024} height={1536} alt="" loading="lazy" decoding="async" /></div></div>
          <div className="kit-item kit-card-ea"><div className="kit-element-motion"><img className="kit-subscription-image" src={imageUrl('kit-ea-play-card-320')} srcSet={`${imageUrl('kit-ea-play-card-320')} 320w, ${imageUrl('kit-ea-play-card-640')} 640w`} sizes="(max-width: 700px) 17vw, (max-width: 1100px) 11vw, 140px" width={1024} height={1536} alt="" loading="lazy" decoding="async" /></div></div>
          <div className="kit-item kit-controller"><div className="kit-element-motion"><img className="kit-controller-image" src={imageUrl('dualsense-cutout')} srcSet={`${imageUrl('dualsense-cutout-560')} 560w, ${imageUrl('dualsense-cutout-1120')} 1120w, ${imageUrl('dualsense-cutout')} 1536w`} sizes="(max-width: 700px) 52vw, (max-width: 1100px) 33vw, 430px" alt={t('Білий геймпад PlayStation DualSense', 'Белый геймпад PlayStation DualSense')} width={1536} height={1024} loading="lazy" decoding="async" /></div></div>
          <div className="kit-item kit-box-fc"><div className="kit-element-motion"><KitGameBox game="fc27" /></div></div>
          <div className="kit-item kit-box-ufc"><div className="kit-element-motion"><KitGameBox game="ufc6" /></div></div>
        </>}
      </div>
      <p className="kit-subscription-note"><strong>PS Plus Deluxe + EA Play</strong><span>{t('На нашому акаунті. Включені в оренду', 'На нашем аккаунте. Включены в аренду')} <span className="kit-console-names">PS5 {t('і', 'и')} PS4.</span></span></p>
    </div>
  </div></section>;
}
