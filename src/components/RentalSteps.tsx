import { ArrowUpRight } from '@phosphor-icons/react';
import { motion } from 'framer-motion';
import { useI18n } from '../lib/i18n';
import { useVisibleMotion } from '../lib/use-visible-motion';
import { StepArtwork, StepRocket } from './StepArtwork';
import '../rental-steps.css';

export function RentalSteps() {
  const { t } = useI18n();
  const { ref, active, reduced } = useVisibleMotion();
  const steps = [
    { art: 'map', title: t('Бронювання', 'Бронь'), copy: t('Обери дати й залиш контакти.', 'Выбери даты и оставь контакты.') },
    { art: 'document', title: t('Оформлення', 'Оформление'), copy: t('Узгодимо вартість, заставу або договір.', 'Согласуем стоимость, залог или договор.') },
    { art: null, title: t('Доставка', 'Доставка'), copy: t('Привеземо та допоможемо підключити.', 'Привезём и поможем подключить.') },
    { art: 'controller', title: t('Грай', 'Играй'), copy: t('Насолоджуйся грою. Решту беремо на себе.', 'Наслаждайся игрой. Остальное берём на себя.') },
  ] as const;
  return <div ref={ref} className="rental-process" data-motion={active ? 'running' : 'paused'}>
    <div className="rental-process-heading"><p className="eyebrow">{t('ЯК ЦЕ ПРАЦЮЄ', 'КАК ЭТО РАБОТАЕТ')}</p><h2>{t('Як взяти', 'Как взять')}<br />{t('в оренду?', 'в аренду?')}</h2></div>
    <ol className="rental-steps">
      {steps.map(({ art, title, copy }, index) => <li key={index}>
        <div className={`rental-step-surface rental-step-${index + 1}`}>
          {index === 2 && <span className="rental-step-badge"><StepRocket />{t('В Одесі', 'В Одессе')}</span>}
          <div className="rental-step-top">
            <motion.span className="rental-step-number" aria-hidden="true" initial={reduced ? false : { opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, amount: .3 }} transition={{ duration: reduced ? 0 : 2, delay: reduced ? 0 : index * .2, ease: [.22, 1, .36, 1] }}>{String(index + 1).padStart(2, '0')}<span>.</span></motion.span>
            {art && <StepArtwork kind={art} />}
          </div>
          <h3>{title}</h3><p>{copy}</p>
        </div>
      </li>)}
    </ol>
    <div className="rental-process-action"><a className="text-link" href="#booking">{t('Обрати дати', 'Выбрать даты')}<ArrowUpRight size={18} aria-hidden="true" /></a></div>
  </div>;
}
