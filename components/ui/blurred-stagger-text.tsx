import { motion } from 'framer-motion';
import { useMotionPreference } from '../../src/lib/motion';

/** Adapted from Preet Suthar's Blurred Stagger Text on 21st.dev. */
export function BlurredStagger({ text, delay = 0 }: { text: string; delay?: number }) {
  const reduced = useMotionPreference();
  const words = text.split(' ');
  const container = {
    hidden: { opacity: 1 },
    show: { opacity: 1, transition: { delayChildren: reduced ? 0 : delay, staggerChildren: reduced ? 0 : 0.015 } },
  };
  const letterAnimation = {
    hidden: { opacity: 0, filter: 'blur(6px)' },
    show: { opacity: 1, filter: 'blur(0px)' },
  };
  return <motion.span className="hero-line" aria-hidden="true" variants={container} initial={reduced ? false : 'hidden'} animate="show">
    {words.map((word, index) => <span className="hero-word-group" key={index}>
      {Array.from(word).map((char, letter) => <motion.span key={letter} className="hero-letter" variants={letterAnimation} transition={{ duration: reduced ? 0 : 0.5, ease: [0.22, 1, 0.36, 1] }}>{char}</motion.span>)}
      {index < words.length - 1 ? <span className="hero-word-space"> </span> : null}
    </span>)}
  </motion.span>;
}
