import { useEffect, useState, type RefObject } from 'react';

// Native lazy loading may fetch several screens ahead. Keep distant decoration
// out of the network queue, while reserving its dimensions in the section.
export function useNearbyMedia(target: RefObject<HTMLElement | null>) {
  const [nearby, setNearby] = useState(false);
  useEffect(() => {
    if (!target.current) return;
    if (!('IntersectionObserver' in window)) { setNearby(true); return; }
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) {
        setNearby(true);
        observer.disconnect();
      }
    }, { rootMargin: '240px' });
    observer.observe(target.current);
    return () => observer.disconnect();
  }, [target]);
  return nearby;
}
