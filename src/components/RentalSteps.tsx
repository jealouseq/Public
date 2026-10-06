import { CalendarBlank, Handshake, Truck, ArrowUpRight } from '@phosphor-icons/react';
import { motion } from 'framer-motion';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { useI18n } from '../lib/i18n';
import { useMotionPreference } from '../lib/motion';
import '../rental-steps.css';

export function RentalSteps() {
  const { t } = useI18n();
  const reduced = useMotionPreference();
  const steps = [
    { Icon: CalendarBlank, title: t('Бронювання', 'Бронирование'), copy: t('Обери консоль, дати та ігри. Залиш контакти — оплата на сайті не потрібна.', 'Выбери консоль, даты и игры. Оставь контакты — оплата на сайте не нужна.') },
    { Icon: Handshake, title: t('Оформлення', 'Оформление'), copy: t('Підтвердимо наявність і вартість. Узгодимо заставу або договір після перевірки документів.', 'Подтвердим наличие и стоимость. Согласуем залог или договор после проверки документов.') },
    { Icon: Truck, title: t('Доставка', 'Доставка'), copy: t('Привеземо комплект у погоджений час і допоможемо з підключенням.', 'Привезём комплект в согласованное время и поможем с подключением.') },
    { Icon: PlayStationController, title: t('Грай', 'Играй'), copy: t('Насолоджуйся своїм вечором. Після оренди заберемо комплект у погоджений час.', 'Наслаждайся своим вечером. После аренды заберём комплект в согласованное время.') },
  ];
  return <div className="rental-process">
    <div className="rental-process-heading"><p className="eyebrow">{t('ЧОТИРИ ПРОСТІ КРОКИ', 'ЧЕТЫРЕ ПРОСТЫХ ШАГА')}</p><h2>{t('Як взяти', 'Как взять')}<br />{t('в оренду?', 'в аренду?')}</h2></div>
    <ol className="rental-steps">
      {steps.map(({ Icon, title, copy }, index) => <li key={title}>
        <motion.div className="rental-step-card" initial={reduced ? false : { opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, amount: .18 }} transition={{ duration: reduced ? 0 : .5, delay: reduced ? 0 : index * .08, ease: [.22, 1, .36, 1] }}>
          <div className="rental-step-surface"><div className="rental-step-top"><span className="rental-step-number" aria-hidden="true">{String(index + 1).padStart(2, '0')}<span>.</span></span><span className="rental-step-icon" aria-hidden="true"><Icon size={Icon === PlayStationController ? 32 : 25} weight="light" /></span></div><h3>{title}</h3><p>{copy}</p></div>
        </motion.div>
      </li>)}
    </ol>
    <div className="rental-process-action"><a className="text-link" href="#booking">{t('Обрати дати', 'Выбрать даты')}<ArrowUpRight size={18} aria-hidden="true" /></a></div>
  </div>;
}
