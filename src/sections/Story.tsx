import { useEffect, useRef, useState } from 'react';
import { useMotionPreference } from '../lib/motion';
import { Check, Truck, CalendarBlank, ArrowUpRight, ArrowUDownLeft, MapPin } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { boot, imageUrl, type StoreSettings } from '../lib/api';
import { money } from '../lib/rental';
import '../delivery.css';
import { DeliveryZoneMap } from '../../components/ui/delivery-zone-map';
import { useI18n } from '../lib/i18n';
import '../story-refinement.css';

export function Kit() {
  const { t } = useI18n();
  const reduced = useMotionPreference();
  const stage = useRef<HTMLDivElement>(null);
  const [inView, setInView] = useState(false);
  const [documentVisible, setDocumentVisible] = useState(() => !document.hidden);
  useEffect(() => {
    const observer = new IntersectionObserver(([entry]) => setInView(entry.isIntersecting && entry.intersectionRatio >= 0.12), { threshold: [0, 0.12] });
    if (stage.current) observer.observe(stage.current);
    const updateVisibility = () => setDocumentVisible(!document.hidden);
    document.addEventListener('visibilitychange', updateVisibility);
    return () => { observer.disconnect(); document.removeEventListener('visibilitychange', updateVisibility); };
  }, []);
  const controllerMedia = {
    srcSet: `${imageUrl('dualsense-cutout-560')} 560w, ${imageUrl('dualsense-cutout-1120')} 1120w, ${imageUrl('dualsense-cutout')} 1536w`,
    sizes: '(min-width: 1280px) 650px, (min-width: 701px) calc(55vw - 56px), (min-width: 478px) 430px, (max-width: 375px) calc(100vw - 40px), calc(100vw - 48px)',
    decoding: 'async' as const,
  };
  const items = [t('PS5 або PS4 та 1–2 геймпади', 'PS5 или PS4 и 1–2 геймпада'), t('HDMI, живлення та зарядний кабель', 'HDMI, питание и зарядный кабель'), t('Інструкція з підключення', 'Инструкция по подключению')];
  return <section className="section kit-section story-kit" id="kit"><div className="shell kit-layout">
    <Reveal className="kit-copy"><p className="eyebrow">{t('У КОМПЛЕКТІ', 'В КОМПЛЕКТЕ')}</p><h2>{t('Усе для', 'Всё для')}<br />{t('першої гри.', 'первой игры.')}</h2><ul className="kit-checklist">{items.map(item => <li key={item}><Check size={18} />{item}</li>)}</ul><p className="kit-note">{t('Другий геймпад — без доплати. Перевіримо комплект і підтвердимо обрані ігри перед орендою.', 'Второй геймпад — без доплаты. Проверим комплект и подтвердим выбранные игры перед арендой.')}</p></Reveal>
    <div ref={stage} className="controller-stage" data-motion={!reduced && inView && documentVisible ? 'running' : 'paused'}><div className="controller-float"><img className="controller-photo" src={imageUrl('dualsense-cutout')} {...controllerMedia} alt={t('Білий DualSense із м’якою теплою підсвіткою', 'Белый DualSense с мягкой тёплой подсветкой')} width={1536} height={1024} loading="lazy" /><img className="controller-light-pass" aria-hidden="true" src={imageUrl('dualsense-cutout')} {...controllerMedia} alt="" width={1536} height={1024} loading="lazy" /></div></div>
  </div></section>;
}
export function HowItWorks({ settings }: { settings: StoreSettings }) {
  const { t, language } = useI18n();
  const steps = [
    [CalendarBlank, t('Обери комплект', 'Выбери комплект'), t('Обери консоль, термін і дату. Додай ігри та залиш контакти.', 'Выбери консоль, срок и дату. Добавь игры и оставь контакты.')],
    [Truck, t('Отримай консоль', 'Получи консоль'), t('Підтвердимо наявність і вартість. Узгодимо доставку, комплектацію та заставу.', 'Подтвердим наличие и стоимость. Согласуем доставку, комплектацию и залог.')],
    [ArrowUDownLeft, t('Грай', 'Играй'), t('Насолоджуйся грою. Заберемо комплект у погоджений час.', 'Наслаждайся игрой. Заберём комплект в согласованное время.')],
  ] as const;
  const delivery = language === 'ru' ? settings.deliveryTextRu : settings.deliveryText;
  const city = language === 'ru' ? settings.cityRu || settings.city : settings.city;
  const zonePrice = (value: number | null | undefined) => value == null ? t('Узгодимо', 'Согласуем') : `${money(value)} грн`;
  return <section className="section how-section story-how" id="how-it-works"><div className="shell">
    <Reveal className="process-heading"><div><p className="eyebrow">{t('ЯК ЦЕ ПРАЦЮЄ', 'КАК ЭТО РАБОТАЕТ')}</p><h2>{t('Від вибору до гри.', 'От выбора до игры.')}</h2></div><a className="text-link" href="#booking">{t('Обрати дати', 'Выбрать даты')}<ArrowUpRight size={18} /></a></Reveal>
    <ol className="process-grid">{steps.map(([Icon, title, description], index) => <li className="process-item" key={index}><Reveal className="process-step" delay={index * 0.08}><span className="process-node" aria-hidden="true"><Icon size={21} weight="light" /></span><div className="process-body"><h3>{title}</h3><p>{description}</p></div></Reveal></li>)}</ol>
    <div className="delivery-panel story-delivery" id="delivery">
      <div className="delivery-copy"><div className="delivery-copy-heading"><p className="delivery-label"><MapPin size={18} weight="light" />{city ? `${t('Доставка', 'Доставка')}: ${city}` : t('ДОСТАВКА ТА ПОВЕРНЕННЯ', 'ДОСТАВКА И ВОЗВРАТ')}</p><h3>{t('Привеземо до тебе.', 'Привезём к тебе.')}</h3></div><figure className="delivery-preview"><img className="delivery-zone-preview" src={`${boot.assetBase}/images/delivery-zone-preview.svg`} alt={t('Схема шести зон доставки в Одесі', 'Схема шести зон доставки в Одессе')} width={160} height={240} loading="lazy" decoding="async" /><span className="delivery-preview-place">{t('Одеса', 'Одесса')}</span></figure><div className="delivery-details"><p>{delivery || t('Вкажи місто та адресу в заявці. Перевіримо можливість доставки й узгодимо час отримання та повернення.', 'Укажи город и адрес в заявке. Проверим возможность доставки и согласуем время получения и возврата.')}</p>{language === 'ru' && !settings.deliveryTextRu && settings.deliveryText && <p className="delivery-original">Условия магазина на украинском: <span lang="uk">{settings.deliveryText}</span></p>}{settings.pickup && <p>{t('Також доступний самовивіз.', 'Также доступен самовывоз.')}</p>}</div></div>
      <div className="delivery-service delivery-zones"><div><h4>{t('Доставка та повернення', 'Доставка и возврат')}</h4><dl className="delivery-zone-prices"><div><dt><span className="zone-dot zone-green" />{t('Зелена зона', 'Зелёная зона')}</dt><dd>{zonePrice(settings.deliveryGreenFee)}</dd></div><div><dt><span className="zone-dot zone-yellow" />{t('Жовта зона', 'Жёлтая зона')}</dt><dd>{zonePrice(settings.deliveryYellowFee)}</dd></div><div><dt><span className="zone-dot zone-red" />{t('Червона зона', 'Красная зона')}</dt><dd>{t('Тариф таксі', 'Тариф такси')}</dd></div></dl><p className="delivery-included">{t('Від', 'От')} {settings.freeDeliveryFrom} {t('днів — зелена та жовта зони безкоштовні.', 'дней — зелёная и жёлтая зоны бесплатно.')}</p><p className="delivery-service-note">{t('Доставка й повернення у ціні. Поза зонами — за погодженням.', 'Доставка и возврат включены в стоимость. Вне зон — по согласованию.')}</p><DeliveryZoneMap freeDeliveryFrom={settings.freeDeliveryFrom} greenFee={settings.deliveryGreenFee ?? null} yellowFee={settings.deliveryYellowFee ?? null} /></div></div>
    </div>
  </div></section>;
}
