import { useRef } from 'react';
import { CalendarBlank, MapPin } from '@phosphor-icons/react';
import { boot, type StoreSettings } from '../lib/api';
import { money } from '../lib/rental';
import { dayGenitiveLabel } from '../lib/copy';
import '../delivery.css';
import { DeliveryZoneMap } from '../../components/ui/delivery-zone-map';
import { useI18n } from '../lib/i18n';
import '../story-refinement.css';
import { useNearbyMedia } from '../lib/use-nearby-media';
import { RentalSteps } from '../components/RentalSteps';

export { RentalKit as Kit } from '../components/RentalKit';

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
