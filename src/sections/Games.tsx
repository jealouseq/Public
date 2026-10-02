import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ArrowRight, ArrowUpRight, Check, Plus, X, Users } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { imageUrl } from '../lib/api';
import type { ConsoleId, Game } from '../lib/rental';

const filters = [['all', 'Усі'], ['two', 'На двох'], ['party', 'Для компанії'], ['racing', 'Перегони'], ['sport', 'Спорт'], ['story', 'Сюжетні'], ['kids', 'Дітям']];
export function Games({ games, consoleId, selected, onToggle }: { games: Game[]; consoleId: ConsoleId; selected: string[]; onToggle: (id: string) => void }) {
  const [filter, setFilter] = useState('all');
  const [detail, setDetail] = useState<Game | null>(null);
  const track = useRef<HTMLDivElement>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const opener = useRef<HTMLElement | null>(null);
  const visible = games.filter(game => game.platforms.includes(consoleId) && (filter === 'all' || game.filters.includes(filter)));
  const close = () => { dialog.current?.close(); setDetail(null); opener.current?.focus(); };
  useEffect(() => { if (detail) dialog.current?.showModal(); }, [detail]);
  useEffect(() => { track.current?.scrollTo({ left: 0 }); }, [filter, consoleId]);
  const scroll = (direction: number) => track.current?.scrollBy({ left: direction * (track.current.clientWidth * 0.7), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  return <section className="section games-section" id="games"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">ОБИРАЙ СВІЙ НАСТРІЙ</p><h2>У що зіграємо?</h2></div><div className="carousel-controls"><span>Гортай та обирай</span><button className="icon-button" onClick={() => scroll(-1)} aria-label="Попередні ігри"><ArrowLeft size={20} /></button><button className="icon-button" onClick={() => scroll(1)} aria-label="Наступні ігри"><ArrowRight size={20} /></button></div></Reveal>
    <div className="game-filters" role="group" aria-label="Категорії ігор">{filters.map(([value, label]) => <button key={value} type="button" className={filter === value ? 'active' : ''} aria-pressed={filter === value} onClick={() => setFilter(value)}>{label}</button>)}</div>
    <div className="game-track" ref={track} role="region" aria-label="Добірка популярних ігор" tabIndex={0}>
      {visible.map((game, index) => <article className="game-card" key={game.id}><button type="button" className={`game-art game-art-${game.image}${selected.includes(game.id) ? ' is-selected' : ''}`} aria-label={`Детальніше про ${game.title}`} onClick={event => { opener.current = event.currentTarget; setDetail(game); }}>
        <img src={(game as Game & { imageUrl?: string }).imageUrl || imageUrl(game.image)} alt={`Ілюстрація для добірки ${game.title}`} width={600} height={900} loading="lazy" decoding="async" />
        <span className="game-age">{game.rating}</span><span className="game-poster-copy"><span>{game.eyebrow}</span><strong>{game.shortTitle}</strong></span>
        <span className="game-open-icon">{selected.includes(game.id) ? <Check size={19} /> : <ArrowUpRight size={19} />}</span>
      </button><div className="game-card-caption"><div><h3>{game.title}</h3><span>{game.genre}</span></div><span className="game-players"><Users size={15} />{game.players}</span></div><span className="sr-only">Ігра {index + 1} із {visible.length}</span></article>)}
      {visible.length === 0 && <p className="empty-state">Для цієї консолі немає ігор у вибраній категорії. Спробуй інший фільтр.</p>}
    </div>
    <p className="section-footnote">Добірка для натхнення. Наявність гри та відповідне видання підтвердимо перед орендою.</p>
  </div>
  {detail && <dialog ref={dialog} className="game-dialog" aria-labelledby="game-dialog-title" onCancel={event => { event.preventDefault(); close(); }} onClick={event => { if (event.target === event.currentTarget) close(); }}>
    <div className="game-dialog-inner"><button className="icon-button dialog-close" onClick={close} aria-label="Закрити опис гри"><X size={22} /></button><img className="dialog-cover" src={(detail as Game & { imageUrl?: string }).imageUrl || imageUrl(detail.image)} alt="Ілюстрація добірки" /><div className="dialog-copy"><p className="eyebrow">{detail.genre} · {detail.rating}</p><h2 id="game-dialog-title">{detail.title}</h2><p>{detail.description}</p><div className="game-meta"><span>{detail.platforms.map(platform => platform.toUpperCase()).join(' / ')}</span><span><Users size={17} />{detail.players === 1 ? '1 гравець' : `До ${detail.players} гравців`}</span></div><p className="muted small">Наявність та умови доступу до гри узгодимо перед орендою.</p><button className="button button-light" onClick={() => { onToggle(detail.id); close(); }}>{selected.includes(detail.id) ? 'Прибрати із заявки' : 'Додати до заявки'}{selected.includes(detail.id) ? <Check size={18} /> : <Plus size={18} />}</button></div></div>
  </dialog>}
  </section>;
}
