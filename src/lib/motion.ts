import { useSyncExternalStore } from 'react';

const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
const subscribe = (notify: () => void) => {
  preference.addEventListener('change', notify);
  return () => preference.removeEventListener('change', notify);
};
// React to preference changes immediately, including while a loop is running.
export const useMotionPreference = () => useSyncExternalStore(subscribe, () => preference.matches, () => true);
