import { useMotionPreference } from '../lib/motion';
import { CaretRight, Truck } from '@phosphor-icons/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { dayLabel, money, type ConsoleId, type Tariff } from '../lib/rental';

import { useI18n } from '../lib/i18n';

export function Tariffs({ consoleId, tariffs, freeDeliveryFrom, onConsole, onChoose }: { freeDeliveryFrom: number; consoleId: ConsoleId; tariffs: Tariff[]; onConsole: (value: ConsoleId) => void; onChoose: (days: number) => void }) {
  const reduced = useMotionPreference();
  const { t, language } = useI18n();
  return <section className="section tariff-section" id="rates"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t('ТАРИФИ', 'ТАРИФЫ')}</p><h2>{t('Обери час для гри.', 'Выбери время для игры.')}</h2></div><ConsoleSwitch value={consoleId} onChange={onConsole} id="tariffs" /></Reveal>
    <AnimatePresence mode="wait" initial={false}><motion.div key={consoleId} className={`tariff-grid tariff-grid-${tariffs.length}`} initial={reduced ? false : { opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={{ duration: reduced ? 0 : 0.22 }}>
      {tariffs.map((tariff) => <article className="tariff" key={tariff.days}><div className="tariff-top"><h3>{language === 'ru' ? tariff.nameRu || tariff.name : tariff.name}</h3></div><p className="tariff-days">{dayLabel(tariff.days, language)}</p><p className="tariff-price"><strong>{money(tariff.price)}</strong><span>грн</span></p><button type="button" className="tariff-link" aria-label={`${t('Обрати', 'Выбрать')} ${consoleId.toUpperCase()} · ${dayLabel(tariff.days, language)}`} onClick={() => onChoose(tariff.days)}>{t('Обрати', 'Выбрать')} <span className="tariff-direction"><CaretRight size={16} weight="light" /></span></button><div className="tariff-benefit">{tariff.days >= freeDeliveryFrom && <><Truck size={16} weight="light" /><span>{t('Доставка включена', 'Доставка включена')}</span></>}</div></article>)}
    </motion.div></AnimatePresence>
    <div className="tariff-conditions"><a className="text-link" href="#delivery">{t('Як отримати консоль', 'Как получить консоль')} <CaretRight size={15} weight="light" /></a></div>
  </div></section>;
}
