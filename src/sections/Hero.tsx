import { ArrowUpRight, ArrowDown } from '@phosphor-icons/react';
import { motion, useReducedMotion, useScroll, useTransform } from 'framer-motion';
import { useRef } from 'react';
import { money } from '../lib/rental';
import { imageUrl } from '../lib/api';

export function Hero({ price, onChoosePS5 }: { price: number; onChoosePS5: () => void }) {
  const ref = useRef<HTMLElement>(null);
  const reduced = useReducedMotion();
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] });
  const y = useTransform(scrollYProgress, [0, 1], [0, 70]);
  return <section className="hero" id="top" ref={ref}>
    <motion.img className="hero-photo" src={imageUrl('ps5-studio')} alt="PlayStation 5 та білий DualSense у темній студії" width={1672} height={941} fetchPriority="high" style={{ y: reduced ? 0 : y }} />
    <div className="shell hero-inner">
      <motion.div className="hero-content" initial={reduced ? false : { opacity: 0, y: 22 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}>
        <p className="eyebrow hero-eyebrow"><span className="status-dot" /> Оренда PlayStation</p>
        <h1>Твій вечір.<br />Твоя<br /><span className="hero-console-word">PlayStation.</span></h1>
        <p className="hero-description">Орендуй PS5 або PS4 на день, вихідні чи довше.<br className="desktop-break" /> Обирай дати — ми подбаємо про решту.</p>
        <div className="hero-actions"><a href="#rates" onClick={onChoosePS5} className="button button-light">Обрати PS5 <ArrowUpRight size={21} /></a><a href="#rates" className="text-link">Дивитися тарифи</a></div>
        <p className="hero-price">Від <strong>{money(price)}</strong><span>грн / 1 день</span></p>
      </motion.div>
      <a className="hero-scroll" href="#rates" aria-label="Перейти до тарифів"><ArrowDown size={19} /><span>Час для гри</span></a>
      <div className="hero-caption"><span>PLAYSTATION 5</span><span>Твій простір. Твоя гра.</span></div>
    </div>
  </section>;
}
