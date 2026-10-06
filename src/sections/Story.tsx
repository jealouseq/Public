import { useEffect, useRef, useState } from 'react';
import { useMotionPreference } from '../lib/motion';
import { Check, CalendarBlank, MapPin } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { boot, imageUrl, type StoreSettings } from '../lib/api';
import { money } from '../lib/rental';
import { dayGenitiveLabel } from '../lib/copy';
import '../delivery.css';
import { DeliveryZoneMap } from '../../components/ui/delivery-zone-map';
import { useI18n } from '../lib/i18n';
import '../story-refinement.css';
import { useNearbyMedia } from '../lib/use-nearby-media';
import { RentalSteps } from '../components/RentalSteps';

export function Kit({ secondFree = false }: { secondFree?: boolean }) {
  const { t } = useI18n();
  const reduced = useMotionPreference();
  const stage = useRef<HTMLDivElement>(null);
  const nearby = useNearbyMedia(stage);
  const [inView, setInView] = useState(false);
  const [documentVisible, setDocumentVisible] = useState(() => !document.hidden);
  useEffect(() => {
    const observer = 'IntersectionObserver' in window ? new IntersectionObserver(([entry]) => setInView(entry.isIntersecting && entry.intersectionRatio >= 0.12), { threshold: [0, 0.12] }) : null;
    if (stage.current) observer?.observe(stage.current);
    const updateVisibility = () => setDocumentVisible(!document.hidden);
    document.addEventListener('visibilitychange', updateVisibility);
    return () => { observer?.disconnect(); document.removeEventListener('visibilitychange', updateVisibility); };
  }, []);
  const controllerMedia = {
    srcSet: `${imageUrl('dualsense-cutout-560')} 560w, ${imageUrl('dualsense-cutout-1120')} 1120w, ${imageUrl('dualsense-cutout')} 1536w`,
    sizes: '(min-width: 1280px) 650px, (min-width: 701px) calc(55vw - 56px), (min-width: 478px) 430px, (max-width: 375px) calc(100vw - 40px), calc(100vw - 48px)',
    decoding: 'async' as const,
  };
  const items = [
    [t('Консоль і геймпади', 'Консоль и геймпады'), t('PS5 або PS4 + 1–2 геймпади', 'PS5 или PS4 + 1–2 геймпада')],
    [t('Усе для підключення', 'Всё для подключения'), t('HDMI-кабель, кабель живлення та заряджання', 'HDMI-кабель, кабель питания и зарядки')],
    [t('Простий старт', 'Простой старт'), t('Коротка інструкція з підключення', 'Краткая инструкция по подключению')],
  ];
  return <section className="section kit-section story-kit" id="kit"><div className="shell kit-layout">
    <Reveal className="kit-copy"><p className="eyebrow">{t('У КОМПЛЕКТІ', 'В КОМПЛЕКТЕ')}</p><h2>{t('Усе готово до гри.', 'Всё готово к игре.')}</h2><ul className="kit-checklist">{items.map(([title, description]) => <li key={title}><Check size={18} aria-hidden="true" /><span><strong>{title}</strong><span>{description}</span></span></li>)}</ul><p className="kit-note">{secondFree && <>{t('Другий геймпад — без доплати.', 'Второй геймпад — без доплаты.')} </>}{t('Кожен комплект перевіряємо перед видачею.', 'Каждый комплект проверяем перед выдачей.')}</p></Reveal>
    <div ref={stage} className="controller-stage" data-motion={!reduced && inView && documentVisible ? 'running' : 'paused'}><div className="controller-float" style={{ aspectRatio: '3 / 2' }}>{nearby && <><img className="controller-photo" style={{ position: 'absolute', inset: 0, height: '100%' }} src={imageUrl('dualsense-cutout')} {...controllerMedia} alt={t('Білий DualSense із м’якою теплою підсвіткою', 'Белый DualSense с мягкой тёплой подсветкой')} width={1536} height={1024} loading="lazy" /><img className="controller-light-pass" aria-hidden="true" src={imageUrl('dualsense-cutout')} {...controllerMedia} alt="" width={1536} height={1024} loading="lazy" /></>}</div></div>
  </div></section>;
}
export function HowItWorks({ settings }: { settings: StoreSettings }) {
  const { t, language } = useI18n();
  const mapPreview = useRef<HTMLDivElement>(null);
  const mapNearby = useNearbyMedia(mapPreview);
  const delivery = language === 'ru' ? settings.deliveryTextRu : settings.deliveryText;
  const city = language === 'ru' ? settings.cityRu || settings.city : settings.city;
  const zonePrice = (value: number | null | undefined) => value == null ? t('Узгодимо', 'Согласуем') : `${money(value)} грн`;
  return <section className="section how-section story-how" id="how-it-works"><div className="shell">
    <RentalSteps />
    <div className="delivery-panel story-delivery" id="delivery">
      <div className="delivery-copy">
        <div className="delivery-copy-heading"><p className="delivery-label"><MapPin size={18} weight="light" />{city ? `${t('Доставка', 'Доставка')}: ${city}` : t('ДОСТАВКА ТА ПОВЕРНЕННЯ', 'ДОСТАВКА И ВОЗВРАТ')}</p><h3>{t('Привеземо до тебе.', 'Привезём к тебе.')}</h3></div>
        <div className="delivery-details"><p>{delivery || t('Вкажи адресу доставки під час бронювання. Перевіримо можливість доставки й узгодимо час отримання та повернення.', 'Укажи адрес доставки при бронировании. Проверим возможность доставки и согласуем время получения и возврата.')}</p>{language === 'ru' && !settings.deliveryTextRu && settings.deliveryText && <p className="delivery-original">Условия магазина на украинском: <span lang="uk">{settings.deliveryText}</span></p>}{settings.pickup && <p>{t('Також доступний самовивіз.', 'Также доступен самовывоз.')}</p>}</div>
      </div>
      <div className="delivery-layout">
        <figure className="delivery-preview">
          <div ref={mapPreview} className="delivery-preview-map">{mapNearby && <><img className="delivery-preview-basemap" src={`${boot.assetBase}/images/delivery-basemap-odessa.webp`} alt="" aria-hidden="true" width={640} height={440} loading="lazy" decoding="async" /><img className="delivery-zone-preview" src={`${boot.assetBase}/images/delivery-zone-map-landscape.svg`} alt={t('Схема шести зон доставки в Одесі', 'Схема шести зон доставки в Одессе')} width={640} height={440} loading="lazy" decoding="async" /><span className="delivery-preview-place">{t('Одеса', 'Одесса')}</span><span className="delivery-preview-sea">{t('Чорне море', 'Чёрное море')}</span></>}</div>
          <figcaption className="delivery-preview-attribution"><a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a></figcaption>
        </figure>
        <div className="delivery-service delivery-zones"><div>
          <h4>{t('Доставка та повернення', 'Доставка и возврат')}</h4>
          <dl className="delivery-zone-prices"><div><dt><span className="zone-dot zone-green" />{t('Зелена зона', 'Зелёная зона')}</dt><dd>{zonePrice(settings.deliveryGreenFee)}</dd></div><div><dt><span className="zone-dot zone-yellow" />{t('Жовта зона', 'Жёлтая зона')}</dt><dd>{zonePrice(settings.deliveryYellowFee)}</dd></div><div><dt><span className="zone-dot zone-red" />{t('Червона зона', 'Красная зона')}</dt><dd>{t('за тарифом таксі', 'по тарифу такси')}</dd></div></dl>
          <p className="delivery-included"><CalendarBlank size={24} weight="light" aria-hidden="true" /><span><strong>{t('При оренді від', 'При аренде от')} {dayGenitiveLabel(settings.freeDeliveryFrom, language)} {t('доставка в зелену та жовту зони — безкоштовна.', 'доставка в зелёную и жёлтую зоны — бесплатно.')}</strong><span className="delivery-service-note">{t('У вартість зони входять доставка та повернення консолі. Поза зонами — за погодженням.', 'В стоимость зоны входят доставка и обратный забор. Вне зон — по согласованию.')}</span></span></p>
        </div></div>
      </div>
      <div className="delivery-map-action"><DeliveryZoneMap freeDeliveryFrom={settings.freeDeliveryFrom} greenFee={settings.deliveryGreenFee ?? null} yellowFee={settings.deliveryYellowFee ?? null} /></div>
    </div>
  </div></section>;
}
