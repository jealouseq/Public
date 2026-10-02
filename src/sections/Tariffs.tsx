import { ArrowUpRight, Truck } from '@phosphor-icons/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { dayLabel, money, type ConsoleId, type Tariff } from '../lib/rental';

export function Tariffs({ consoleId, tariffs, freeDeliveryFrom, onConsole, onChoose }: { freeDeliveryFrom: number; consoleId: ConsoleId; tariffs: Tariff[]; onConsole: (value: ConsoleId) => void; onChoose: (days: number) => void }) {
  const reduced = useReducedMotion();
  return <section className="section tariff-section" id="rates"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">КОНСОЛЬ + ТВІЙ ЧАС</p><h2>Обери свій час<br className="tablet-break" /> для гри.</h2></div><ConsoleSwitch value={consoleId} onChange={onConsole} id="tariffs" /></Reveal>
    <AnimatePresence mode="wait" initial={false}><motion.div key={consoleId} className={`tariff-grid tariff-grid-${tariffs.length}`} initial={reduced ? false : { opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={{ duration: 0.22 }}>
      {tariffs.map((tariff) => <article className="tariff" key={tariff.days}><div className="tariff-top"><h3>{tariff.name}</h3>{tariff.days === 3 && <span className="small-dot" aria-label="Рекомендуємо для першої оренди" />}</div><p className="tariff-days">{dayLabel(tariff.days)}</p><p className="tariff-price"><strong>{money(tariff.price)}</strong><span>грн</span></p><p className="tariff-description">{tariff.description}</p><button type="button" className="text-link tariff-link" onClick={() => onChoose(tariff.days)}>Обрати <ArrowUpRight size={18} /></button></article>)}
    </motion.div></AnimatePresence>
    <div className="tariff-note"><Truck size={19} weight="light" /><span>Від {freeDeliveryFrom} днів — доставка та підключення без доплат у зоні сервісу.</span><a href="#delivery">Умови доставки <ArrowUpRight size={14} /></a></div>
  </div></section>;
}
