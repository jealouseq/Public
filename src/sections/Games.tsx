import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ArrowRight, ArrowUpRight, Check, Users } from '@phosphor-icons/react';
import { Reveal } from '../../components/ui/reveal';
import { GameCover, GameDetailDialog, GameFilters } from '../components/GamePresentation';
import { localPlayers, matchesGameFilter, type ConsoleId, type Game } from '../lib/rental';
import type { CatalogStatus } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { localPlayerLabel } from '../lib/copy';

export function Games({ games, consoleId, selected, onToggle, catalogStatus, maxGames }: { maxGames: number; games: Game[]; consoleId: ConsoleId; selected: string[]; onToggle: (id: string) => void; catalogStatus: CatalogStatus }) {
  const { t, language } = useI18n();
  const [filter, setFilter] = useState('all');
  const [detail, setDetail] = useState<Game | null>(null);
  const [coverWidth, setCoverWidth] = useState(320);
  const track = useRef<HTMLDivElement>(null);
  const opener = useRef<HTMLElement | null>(null);
  const visible = games.filter(game => matchesGameFilter(game, consoleId, filter));
  const close = () => { setDetail(null); requestAnimationFrame(() => opener.current?.focus({ preventScroll: true })); };
  useEffect(() => { track.current?.scrollTo({ left: 0 }); }, [filter, consoleId]);
  useEffect(() => { if (detail && !games.some(game => game.id === detail.id && game.platforms.includes(consoleId))) close(); }, [games, consoleId, detail]);
  const scroll = (direction: number) => track.current?.scrollBy({ left: direction * (track.current.clientWidth * 0.7), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  return <section className="section games-section" id="games"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t('ІГРИ ДО ТВОЄЇ КОНСОЛІ', 'ИГРЫ ДЛЯ ТВОЕЙ КОНСОЛИ')}</p><h2>{t('У що зіграємо?', 'Во что сыграем?')}</h2></div><div className="carousel-controls"><span>{t('Гортай і обирай', 'Листай и выбирай')}</span><button type="button" className="icon-button" onClick={() => scroll(-1)} aria-label={t('Попередні ігри', 'Предыдущие игры')}><ArrowLeft size={20} /></button><button type="button" className="icon-button" onClick={() => scroll(1)} aria-label={t('Наступні ігри', 'Следующие игры')}><ArrowRight size={20} /></button></div></Reveal>
    <GameFilters value={filter} onChange={setFilter} />
    <div className="game-track" ref={track} role="region" aria-label={t('Добірка популярних ігор', 'Подборка популярных игр')} tabIndex={0}>
      {visible.map(game => <article className="game-card" key={game.id}><button type="button" className={`game-art${selected.includes(game.id) ? ' is-selected' : ''}`} aria-label={`${t('Детальніше про', 'Подробнее об игре')} ${game.title}`} onClick={event => { opener.current = event.currentTarget; setCoverWidth(event.currentTarget.clientWidth); setDetail(game); }}><GameCover game={game} /><span className="game-open-icon">{selected.includes(game.id) ? <Check size={19} /> : <ArrowUpRight size={19} />}</span></button><div className="game-card-caption"><div><h3>{game.title}</h3><span>{language === 'ru' ? game.genreRu || 'Игра для PlayStation' : game.genre}</span></div><span className="game-players" aria-label={localPlayerLabel(localPlayers(game, consoleId), language)}><Users size={15} />{localPlayers(game, consoleId)}</span></div></article>)}
      {visible.length === 0 && <p className="empty-state">{t('Для цієї консолі немає ігор у вибраній категорії. Спробуй інший фільтр.', 'Для этой консоли нет игр в выбранной категории. Попробуй другой фильтр.')}</p>}
    </div><p className="section-footnote">{t('Обери ігри — наявність і версію підтвердимо перед видачею.', 'Выбери игры — наличие и версию подтвердим перед выдачей.')}</p>
  </div>{detail && <GameDetailDialog game={detail} consoleId={consoleId} selected={selected.includes(detail.id)} coverWidth={coverWidth} disabled={catalogStatus !== 'ready' || (!selected.includes(detail.id) && selected.length >= maxGames)} disabledMessage={!selected.includes(detail.id) && selected.length >= maxGames ? t('Досягнуто ліміту ігор. Прибери одну, щоб додати іншу.', 'Достигнут лимит игр. Убери одну, чтобы добавить другую.') : undefined} onToggle={() => onToggle(detail.id)} onClose={close} />}</section>;
}
