import { Check, Plus } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { useNearbyMedia } from '../lib/use-nearby-media';
import { useVisibleMotion } from '../lib/use-visible-motion';
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
        <div className="kit-subscription-cards">
          <div className="kit-subscription kit-deluxe"><Plus size={27} weight="bold" aria-hidden="true" /><span>PS Plus Deluxe</span><span className="kit-card-glyphs" aria-hidden="true">△ ○ × □</span></div>
          <div className="kit-subscription kit-ea"><span className="kit-ea-monogram" aria-hidden="true">EA</span><span>EA Play</span><span className="kit-ea-ring" aria-hidden="true" /></div>
        </div>
        {nearby && <img className="kit-product-image" src={imageUrl('joyrent-console-kit-768')} srcSet={`${imageUrl('joyrent-console-kit-480')} 480w, ${imageUrl('joyrent-console-kit-768')} 768w, ${imageUrl('joyrent-console-kit')} 1536w`} sizes="(max-width: 700px) calc(100vw - 48px), (max-width: 1100px) 52vw, 650px" alt={t('Комплект PlayStation: PS5 або PS4 та геймпади', 'Комплект PlayStation: PS5 или PS4 и геймпады')} width={1536} height={1024} loading="lazy" decoding="async" />}
      </div>
      <p className="kit-subscription-note">{t('Підписки на нашому акаунті включені в оренду PS5 і PS4.', 'Подписки на нашем аккаунте включены в аренду PS5 и PS4.')}</p>
    </div>
  </div></section>;
}
