// Adapted from the supplied Scroll Reveal Image prompt by unlumen on 21st.dev.
// WordPress provides optimized image URLs in place of Next.js Image.
import { useRef } from 'react';
import { motion, useReducedMotion, useScroll, useSpring, useTransform } from 'framer-motion';

export interface ScrollRevealImageProps {
  src: string; alt: string; height?: string; fromWidth?: string; toWidth?: string;
  fromRadius?: string; toRadius?: string; radiusStart?: number;
  fromScale?: number; toScale?: number; stiffness?: number; damping?: number;
  className?: string;
}
export default function ScrollRevealImage({
  src, alt, height = 'min(68vw, 640px)', fromWidth = '76%', toWidth = '100%',
  fromRadius = '30px', toRadius = '0px', radiusStart = 0.5,
  fromScale = 1.12, toScale = 1, stiffness = 120, damping = 80, className,
}: ScrollRevealImageProps) {
  const target = useRef<HTMLDivElement>(null);
  const reduced = useReducedMotion();
  const { scrollYProgress } = useScroll({ target, offset: ['start end', 'start start'] });
  const progress = useSpring(scrollYProgress, { stiffness, damping });
  const width = useTransform(progress, [0, 1], [fromWidth, toWidth]);
  const radius = useTransform(progress, [radiusStart, 1], [fromRadius, toRadius]);
  const scale = useTransform(progress, [0, 1], [fromScale, toScale]);
  return <div ref={target} className={className} style={{ height }}>
    <motion.div className="scroll-photo" style={{ width: reduced ? '100%' : width, height: '100%', borderRadius: reduced ? 0 : radius }}>
      <motion.img src={src} alt={alt} width={1672} height={941} loading="lazy" decoding="async" style={{ scale: reduced ? 1 : scale }} />
    </motion.div>
  </div>;
}
