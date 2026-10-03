import type { ComponentPropsWithoutRef } from 'react';

// JOYRENT's original DualSense silhouette, matching the site's light outline icons.
export function PlayStationController({ size = 24, weight: _weight, className, ...props }: ComponentPropsWithoutRef<'svg'> & { size?: number; weight?: 'light' }) {
  return <svg width={size} height={size} viewBox="0 0 24 24" strokeWidth="1.3" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" className={className} {...props}>
    <path d="M6.1 6.4C3.8 6.4 2.5 8.4 1.9 11.6l-.6 4.6c-.2 1.9.3 3.4 1.6 3.4 1.5 0 2.5-2 3.7-3.5.6-.8 1.5-1.2 2.5-1.2h5.8c1 0 1.9.4 2.5 1.2 1.2 1.5 2.2 3.5 3.7 3.5 1.3 0 1.8-1.5 1.6-3.4l-.6-4.6c-.6-3.2-1.9-5.2-4.2-5.2H6.1Z" />
    <path d="M7.8 6.5h8.4l-.5 3.3c-.1.6-.5 1-1.1 1H9.4c-.6 0-1-.4-1.1-1l-.5-3.3Z" />
    <path d="M4.9 8.7v3.1M3.4 10.2h3" />
    <circle cx="9.1" cy="13.5" r="1.35" /><circle cx="14.9" cy="13.5" r="1.35" />
    <path d="M19.1 8.6h.01m0 3.1h.01m-1.6-1.55h.01m3.1 0h.01" strokeWidth="1.9" />
    <path d="m4.6 6.6.4-1h2.1m9.8 0H19l.4 1" />
  </svg>;
}
