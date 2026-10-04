import { useEffect, useRef, useState } from 'react';
import { useMotionPreference } from '../lib/motion';
import { Check, Truck, CalendarBlank, ArrowUpRight, MapPin } from '@phosphor-icons/react';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { Reveal } from '../../components/ui/reveal';
import { boot, imageUrl, type StoreSettings } from '../lib/api';
import { money } from '../lib/rental';
import { dayGenitiveLabel } from '../lib/copy';
import '../delivery.css';
import { DeliveryZoneMap } from '../../components/ui/delivery-zone-map';
import { useI18n } from '../lib/i18n';
import '../story-refinement.css';
import { useNearbyMedia } from '../lib/use-nearby-media';

export function Kit() {
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
  const items = [t('PS5 або PS4 + 1–2 геймпади', 'PS5 или PS4 + 1–2 геймпада'), t('HDMI-кабель, кабель живлення та заряджання', 'HDMI-кабель, кабель питания и зарядки'), t('Коротка інструкція з підключення', 'Краткая инструкция по подключению')];
  return <section className="section kit-section story-kit" id="kit"><div className="shell kit-layout">
    <Reveal className="kit-copy"><p className="eyebrow">{t('У КОМПЛЕКТІ', 'В КОМПЛЕКТЕ')}</p><h2>{t('Усе готово до гри.', 'Всё готово к игре.')}</h2><ul className="kit-checklist">{items.map(item => <li key={item}><Check size={18} />{item}</li>)}</ul><p className="kit-note">{t('Другий геймпад — без доплати. Кожен комплект перевіряємо перед видачею.', 'Второй геймпад — без доплаты. Каждый комплект проверяем перед выдачей.')}</p></Reveal>
    <div ref={stage} className="controller-stage" data-motion={!reduced && inView && documentVisible ? 'running' : 'paused'}><div className="controller-float" style={{ aspectRatio: '3 / 2' }}>{nearby && <><img className="controller-photo" style={{ position: 'absolute', inset: 0, height: '100%' }} src={imageUrl('dualsense-cutout')} {...controllerMedia} alt={t('Білий DualSense із м’якою теплою підсвіткою', 'Белый DualSense с мягкой тёплой подсветкой')} width={1536} height={1024} loading="lazy" /><img className="controller-light-pass" aria-hidden="true" src={imageUrl('dualsense-cutout')} {...controllerMedia} alt="" width={1536} height={1024} loading="lazy" /></>}</div></div>
  </div></section>;
}
export function HowItWorks({ settings }: { settings: StoreSettings }) {
  const { t, language } = useI18n();
  const mapPreview = useRef<HTMLDivElement>(null);
  const mapNearby = useNearbyMedia(mapPreview);
  const steps = [
    [CalendarBlank, t('Обери комплект', 'Выбери комплект'), t('Обери консоль, термін і дати. Додай ігри та залиш контакти.', 'Выбери консоль, срок и даты. Добавь игры и оставь контакты.')],
    [Truck, t('Отримай консоль', 'Получи консоль'), t('Підтвердимо наявність і підсумкову вартість. Узгодимо доставку та оформлення оренди.', 'Подтвердим наличие и итоговую стоимость. Согласуем доставку и оформление аренды.')],
    [PlayStationController, t('Грай', 'Играй'), t('Грай у своє задоволення. Заберемо комплект у погоджений час.', 'Наслаждайся игрой. Заберём комплект в согласованное время.')],
  ] as const;
  const delivery = language === 'ru' ? settings.deliveryTextRu : settings.deliveryText;
  const city = language === 'ru' ? settings.cityRu || settings.city : settings.city;
  const zonePrice = (value: number | null | undefined) => value == null ? t('Узгодимо', 'Согласуем') : `${money(value)} грн`;
  return <section className="section how-section story-how" id="how-it-works"><div className="shell">
    <Reveal className="process-heading"><div><p className="eyebrow">{t('ЯК ЦЕ ПРАЦЮЄ', 'КАК ЭТО РАБОТАЕТ')}</p><h2>{t('Від вибору до гри.', 'От выбора до игры.')}</h2></div><a className="text-link" href="#booking">{t('Обрати дати', 'Выбрать даты')}<ArrowUpRight size={18} /></a></Reveal>
    <ol className="process-grid">{steps.map(([Icon, title, description], index) => <li className="process-item" key={index}><Reveal className="process-step" delay={index * 0.08}><div className="process-body"><div className="process-title-row"><span className="process-node" aria-hidden="true"><Icon size={Icon === PlayStationController ? 28 : 21} weight="light" /></span><h3>{title}</h3></div><p>{description}</p></div></Reveal></li>)}</ol>
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
