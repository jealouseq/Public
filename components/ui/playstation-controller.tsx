import type { ComponentPropsWithoutRef } from 'react';

// Vector adaptation of the selected ImageGen design: ivory grips and gold touchpad.
// One shared icon keeps the rental steps, controller choices and cover fallback consistent.
export function PlayStationController({ size = 24, weight: _weight, className, ...props }: ComponentPropsWithoutRef<'svg'> & { size?: number; weight?: 'light' }) {
  return <svg width={size} height={size} viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" className={['playstation-controller', className].filter(Boolean).join(' ')} {...props}>
    <path d="M18.5 13.3h27l8.3 35.4c-4-7.7-6.3-11-9.3-11h-25c-3 0-5.3 3.3-9.3 11Z" fill="#141516" />
    <g fill="#f4f2e8">
      <path d="M17.6 13c-3.5.3-7.5 1.4-10.2 4.1C4.9 21.6 1 32.9 1 42.2c0 6.2 2.9 8.8 6.6 9.6 1.4.3 1.8-.6 2.3-1.5C13.5 40.8 15.2 33.3 20.6 27.4c.7-1.4.6-2.4.2-4l-2-9.1c-.2-1-.5-1.4-1.2-1.3Z" />
      <path d="M17.6 13c-3.5.3-7.5 1.4-10.2 4.1C4.9 21.6 1 32.9 1 42.2c0 6.2 2.9 8.8 6.6 9.6 1.4.3 1.8-.6 2.3-1.5C13.5 40.8 15.2 33.3 20.6 27.4c.7-1.4.6-2.4.2-4l-2-9.1c-.2-1-.5-1.4-1.2-1.3Z" transform="translate(64 0) scale(-1 1)" />
    </g>
    <path d="M21 13.3h22c1.5 0 2.2.6 1.8 2.2L43 21.2c-.8 2.7-2.3 4-4.6 4H25.6c-2.3 0-3.8-1.3-4.6-4l-1.8-5.7c-.4-1.6.3-2.2 1.8-2.2Z" fill="#c8a96b" />
    <g fill="#0c0e0f">
      <rect x="11.3" y="19.2" width="3.2" height="9.8" rx="1.3" />
      <rect x="8" y="22.5" width="9.8" height="3.2" rx="1.3" />
      <circle cx="51.2" cy="19.5" r="1.8" />
      <circle cx="47.5" cy="23.3" r="1.8" />
      <circle cx="54.9" cy="23.3" r="1.8" />
      <circle cx="51.2" cy="27.1" r="1.8" />
    </g>
    <g fill="#141516" stroke="#f4f2e8" strokeWidth="1.4">
      <circle cx="22.5" cy="31.8" r="3.3" />
      <circle cx="41.5" cy="31.8" r="3.3" />
    </g>
    <rect x="30.2" y="31.3" width="3.6" height="1" rx=".35" fill="#787a78" />
  </svg>;
}
