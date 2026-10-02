import { useMotionPreference } from '../lib/motion';
import { Check, Truck, CalendarBlank, ArrowUpRight, MapPin, GameController } from '@phosphor-icons/react';
import { motion } from 'framer-motion';
import { Reveal } from '../../components/ui/reveal';
import { imageUrl, type StoreSettings } from '../lib/api';
import { useI18n } from '../lib/i18n';

export function Kit() {
  const { t } = useI18n();
  const reduced = useMotionPreference();
  const items = [t('PS5 або PS4 та геймпад', 'PS5 или PS4 и геймпад'), t('HDMI, живлення та зарядний кабель', 'HDMI, питание и зарядный кабель'), t('Інструкція з підключення', 'Инструкция по подключению')];
  return <section className="section kit-section" id="kit"><div className="shell kit-layout">
    <Reveal className="kit-copy"><p className="eyebrow">{t('У КОМПЛЕКТІ', 'В КОМПЛЕКТЕ')}</p><h2>{t('Усе для', 'Всё для')}<br />{t('першої гри.', 'первой игры.')}</h2><ul className="kit-checklist">{items.map(item => <li key={item}><Check size={18} />{item}</li>)}</ul><p className="kit-note">{t('Другий геймпад та бажані ігри додай до заявки. Перед передачею перевіримо обладнання й узгодимо комплектацію.', 'Второй геймпад и желаемые игры добавь в заявку. Перед передачей проверим оборудование и согласуем комплектацию.')}</p></Reveal>
    <div className="controller-stage"><motion.div className="controller-float" initial={reduced ? false : { y: 0, rotate: 0, rotateY: 0 }} animate={reduced ? { y: 0, rotate: 0, rotateY: 0 } : { y: [0, -6, 0, 4, 0], rotate: [0, -0.6, 0, 0.6, 0], rotateY: [0, -2, 0, 2, 0] }} transition={{ duration: reduced ? 0 : 12, repeat: reduced ? 0 : Infinity, ease: 'easeInOut' }}><img className="controller-photo" src={imageUrl('dualsense-cutout')} alt={t('Білий DualSense із м’якою теплою підсвіткою', 'Белый DualSense с мягкой тёплой подсветкой')} width={1536} height={1024} loading="lazy" /><motion.img className="controller-light-pass" aria-hidden="true" src={imageUrl('dualsense-cutout')} alt="" width={1536} height={1024} loading="lazy" initial={reduced ? false : { opacity: 0.02 }} animate={{ opacity: reduced ? 0.05 : [0.02, 0.12, 0.02] }} transition={{ duration: reduced ? 0 : 12, repeat: reduced ? 0 : Infinity, ease: 'easeInOut' }} /></motion.div><span className="controller-caption">DualSense · PlayStation 5</span></div>
  </div></section>;
}
export function HowItWorks({ settings }: { settings: StoreSettings }) {
  const { t, language } = useI18n();
  const steps = [
    [CalendarBlank, t('Обери', 'Выбери'), t('Консоль, термін і дату. Залиш контакти — оплата на цьому кроці не потрібна.', 'Консоль, срок и дату. Оставь контакты — оплата на этом шаге не нужна.')],
    [Truck, t('Отримай', 'Получи'), t('Підтвердимо наявність і вартість. Узгодимо доставку, комплектацію та заставу.', 'Подтвердим наличие и стоимость. Согласуем доставку, комплектацию и залог.')],
    [GameController, t('Грай', 'Играй'), t('Підключай і грай. Час повернення погодимо під час підтвердження оренди.', 'Подключай и играй. Время возврата согласуем при подтверждении аренды.')],
  ] as const;
  const delivery = language === 'ru' ? settings.deliveryTextRu : settings.deliveryText;
  return <section className="section how-section" id="how-it-works"><div className="shell">
    <Reveal className="process-heading"><div><p className="eyebrow">{t('ЯК ЦЕ ПРАЦЮЄ', 'КАК ЭТО РАБОТАЕТ')}</p><h2>{t('Від вибору до гри.', 'От выбора до игры.')}</h2></div><a className="text-link" href="#booking">{t('Обрати дати', 'Выбрать даты')}<ArrowUpRight size={18} /></a></Reveal>
    <ol className="process-grid">{steps.map(([Icon, title, description], index) => <li className="process-item" key={index}><Reveal delay={index * 0.08}><div className="process-top"><Icon size={28} weight="light" /><h3>{title}</h3></div><p>{description}</p></Reveal></li>)}</ol>
    <div className="delivery-panel" id="delivery"><div className="delivery-heading"><MapPin size={23} weight="light" /><h3>{settings.city ? `${t('Доставка', 'Доставка')}: ${settings.city}` : t('Доставка та повернення', 'Доставка и возврат')}</h3></div><div className="delivery-details"><p>{delivery || t('Вкажи місто та адресу в заявці. Перевіримо можливість доставки.', 'Укажи город и адрес в заявке. Проверим возможность доставки.')}</p>{language === 'ru' && !settings.deliveryTextRu && settings.deliveryText && <p className="delivery-original">Условия магазина на украинском: <span lang="uk">{settings.deliveryText}</span></p>}{settings.pickup && <p>{t('Також доступний самовивіз.', 'Также доступен самовывоз.')}</p>}</div><p className="delivery-benefit"><strong>{t('Від', 'От')} {settings.freeDeliveryFrom} {t('днів — безкоштовно', 'дней — бесплатно')}</strong><span>{t('Доставка й підключення в зоні сервісу. Для коротшої оренди вартість узгодимо за адресою.', 'Доставка и подключение в зоне сервиса. Для короткой аренды стоимость согласуем по адресу.')}</span></p></div>
  </div></section>;
}
