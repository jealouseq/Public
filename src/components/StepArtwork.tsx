import { imageUrl } from '../lib/api';

export function StepArtwork({ kind }: { kind: 'map' | 'document' | 'controller' }) {
  if (kind === 'map') return <span className="step-art step-map" aria-hidden="true"><svg viewBox="0 0 80 80">
    <g className="step-visual-motion step-map-disc"><circle cx="36" cy="46" r="25" fill="#d9dee2" /><path d="M14 57 58 33M22 24 47 69" stroke="#fff" strokeWidth="5" /><path d="m42 22 18 12-11 8-8-16" fill="#b4bbc2" /></g>
    <g className="step-visual-motion step-map-pin"><path d="M54 7c-11 0-19 8-19 18 0 13 19 30 19 30s19-17 19-30C73 15 65 7 54 7Z" fill="#ed7a8e" /><circle cx="54" cy="25" r="7" fill="#fff" /></g>
  </svg></span>;
  if (kind === 'document') return <span className="step-art step-document" aria-hidden="true"><svg viewBox="0 0 80 80" className="step-visual-motion step-document-float">
    <rect x="19" y="8" width="42" height="58" rx="7" fill="#dce2e7" transform="rotate(-8 40 37)" /><path d="m28 25 21-3m-19 14 20-3m-18 14 13-2" stroke="#7c8591" strokeWidth="3" strokeLinecap="round" />
    <circle cx="57" cy="57" r="13" fill="#eab56e" /><path d="m51 57 4 4 8-9" fill="none" stroke="#2b2015" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
  </svg></span>;
  return <span className="step-art step-controller" aria-hidden="true"><img className="step-visual-motion step-controller-image" src={imageUrl('dualsense-cutout-560')} width={560} height={373} alt="" loading="lazy" decoding="async" /><span className="step-visual-motion step-game-symbol symbol-triangle">△</span><span className="step-visual-motion step-game-symbol symbol-circle">○</span><span className="step-visual-motion step-game-symbol symbol-cross">×</span><span className="step-visual-motion step-game-symbol symbol-square">□</span></span>;
}

export function StepRocket() {
  return <svg className="step-visual-motion step-rocket" aria-hidden="true" viewBox="0 0 40 40"><g transform="rotate(35 20 20)">
    <path d="M14 27 20 39 26 27" fill="#ffe8a7" /><path d="m17 28 3 8 3-8" fill="#ef8c52" />
    <path d="M13 19 6 28l8-1m13-8 7 9-8-1" fill="#ec7487" /><path d="M20 2C9 12 11 23 15 29h10c4-6 6-17-5-27Z" fill="#f4f5f5" /><path d="M20 2c-3 3-5 6-6 9h12c-1-3-3-6-6-9Z" fill="#ec7487" /><circle cx="20" cy="17" r="4" fill="#7997ae" /><circle cx="20" cy="17" r="2.5" fill="#c9e4f2" />
  </g></svg>;
}
