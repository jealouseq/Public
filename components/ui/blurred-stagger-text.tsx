import type { CSSProperties } from 'react';

/** Native CSS entrance inspired by 21st.dev; preserve the existing component API. */
export function BlurredStagger({ text, delay = 0 }: { text: string; delay?: number }) {
  const words = text.split(' ');
  return <span className="hero-line" aria-hidden="true">
    {words.map((word, index) => <span className="hero-word" key={index}>
      <span className="hero-word-group" style={{ '--word-delay': `${delay + index * 0.11}s` } as CSSProperties}>{Array.from(word).map((char, letter) => <span key={letter} className="hero-letter">{char}</span>)}</span>
      {index < words.length - 1 ? ' ' : null}
    </span>)}
  </span>;
}
