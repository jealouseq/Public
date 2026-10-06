import { useEffect, useRef, useState } from 'react';
import { useMotionPreference } from './motion';

export function useVisibleMotion() {
  const ref = useRef<HTMLDivElement>(null);
  const reduced = useMotionPreference();
  const [visible, setVisible] = useState(false);
  const [documentVisible, setDocumentVisible] = useState(() => !document.hidden);
  useEffect(() => {
    const observer = 'IntersectionObserver' in window
      ? new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting), { threshold: .1 })
      : null;
    if (ref.current) observer?.observe(ref.current);
    if (!observer) setVisible(true);
    const update = () => setDocumentVisible(!document.hidden);
    document.addEventListener('visibilitychange', update);
    return () => { observer?.disconnect(); document.removeEventListener('visibilitychange', update); };
  }, []);
  return { ref, reduced, active: visible && documentVisible && !reduced };
}
