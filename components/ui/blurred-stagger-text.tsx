import type { CSSProperties } from 'react';

/** Native CSS entrance inspired by 21st.dev; preserve the existing component API. */
export function BlurredStagger({ text, delay = 0 }: { text: string; delay?: number }) {
  const words = text.split(' ');
  let position = 0;
  return <span className="hero-line" aria-hidden="true">
    {words.map((word, index) => <span className="hero-word-group" key={index}>
      {Array.from(word).map((char, letter) => <span key={letter} className="hero-letter" style={{ '--letter-delay': `${delay + position++ * 0.012}s` } as CSSProperties}>{char}</span>)}
      {index < words.length - 1 ? <span className="hero-word-space"> </span> : null}
    </span>)}
  </span>;
}
