import { useMotionPreference } from '../lib/motion';
import { PlugsConnected, Check, Truck, CalendarBlank, ArrowUpRight, MapPin, ArrowUUpLeft } from '@phosphor-icons/react';
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
    <div className="controller-stage"><motion.img src={imageUrl('dualsense-cutout')} alt={t('Білий DualSense із теплою підсвіткою, повністю без фону', 'Белый DualSense с тёплой подсветкой, целиком без фона')} width={1536} height={1024} loading="lazy" initial={reduced ? false : { y: 0, rotate: 0 }} animate={reduced ? { y: 0, rotate: 0 } : { y: [0, -7, 0], rotate: [-1, 0.8, -1] }} transition={{ duration: reduced ? 0 : 8, repeat: reduced ? 0 : Infinity, ease: 'easeInOut' }} /><span className="controller-caption">DualSense · PlayStation 5</span></div>
  </div></section>;
}
export function HowItWorks({ settings }: { settings: StoreSettings }) {
  const { t, language } = useI18n();
  const steps = [[CalendarBlank, t('Обери та надішли заявку', 'Выбери и отправь заявку'), t('Консоль, термін, дата й контакти. На цьому кроці оплата не потрібна.', 'Консоль, срок, дата и контакты. На этом шаге оплата не нужна.')], [Truck, t('Узгодимо та передамо', 'Согласуем и передадим'), t('Перевіримо доступність, комплектацію, доставку та заставу. Підтвердження — особисто з тобою.', 'Проверим доступность, комплектацию, доставку и залог. Подтверждение — лично с тобой.')], [ArrowUUpLeft, t('Грай і повертай', 'Играй и возвращай'), t('Час і спосіб повернення домовимося заздалегідь. Для продовження зв’яжися з нами до кінця оренди.', 'Время и способ возврата согласуем заранее. Для продления свяжись с нами до конца аренды.')]] as const;
  return <section className="section how-section" id="how-it-works"><div className="shell"><Reveal className="section-heading"><div><p className="eyebrow">{t('ЯК ЦЕ ПРАЦЮЄ', 'КАК ЭТО РАБОТАЕТ')}</p><h2>{t('Оренда без зайвих кроків.', 'Аренда без лишних шагов.')}</h2></div></Reveal><div className="process-grid">{steps.map(([Icon, title, description], index) => <Reveal key={title} delay={index * 0.06} className="process-item"><Icon size={30} weight="light" /><h3>{title}</h3><p>{description}</p></Reveal>)}</div>
    <div className="delivery-strip" id="delivery"><div><MapPin size={22} weight="light" /><h3>{settings.city ? `${t('Доставка', 'Доставка')}: ${settings.city}` : t('Доставка за адресою', 'Доставка по адресу')}</h3><p>{language === 'ru' ? settings.deliveryTextRu || t('', 'Укажи город и адрес в заявке. Проверим возможность доставки и согласуем время получения и возврата.') : settings.deliveryText}</p>{language === 'ru' && !settings.deliveryTextRu && settings.deliveryText && <p className="delivery-original">Условия магазина на украинском: <span lang="uk">{settings.deliveryText}</span></p>}{settings.pickup && <p>{t('Також доступний самовивіз.', 'Также доступен самовывоз.')}</p>}</div><div><PlugsConnected size={22} weight="light" /><h3>{t('Від', 'От')} {settings.freeDeliveryFrom} {t('днів — доставка включена', 'дней — доставка включена')}</h3><p>{t('Доставка й підключення безкоштовні в зоні сервісу. Для коротших термінів вартість узгодимо за адресою.', 'Доставка и подключение бесплатны в зоне сервиса. Для коротких сроков стоимость согласуем по адресу.')}</p></div><a className="text-link" href="#booking">{t('Перейти до заявки', 'Перейти к заявке')}<ArrowUpRight size={18} /></a></div>
  </div></section>;
}
