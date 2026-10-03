import { useEffect, useRef, useState, type CSSProperties } from 'react';
import { ArrowLeft, ArrowRight, ArrowUpRight, Check, Plus, X, Users } from '@phosphor-icons/react';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { Reveal } from '../../components/ui/reveal';
import { imageUrl } from '../lib/api';
import type { ConsoleId, Game } from '../lib/rental';
import { useI18n } from '../lib/i18n';

export function Games({ games, consoleId, selected, onToggle }: { games: Game[]; consoleId: ConsoleId; selected: string[]; onToggle: (id: string) => void }) {
  const { t, language } = useI18n();
  const filters = [['all', t('Усі', 'Все')], ['two', t('На двох', 'Вдвоём')], ['party', t('Для компанії', 'Для компании')], ['racing', t('Перегони', 'Гонки')], ['sport', t('Спорт', 'Спорт')], ['story', t('Сюжетні', 'Сюжетные')], ['kids', t('Дітям', 'Детям')]];
  const [filter, setFilter] = useState('all');
  const [detail, setDetail] = useState<Game | null>(null);
  const [coverWidth, setCoverWidth] = useState(320);
  const [viewportWidth, setViewportWidth] = useState(window.innerWidth);
  const track = useRef<HTMLDivElement>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const opener = useRef<HTMLElement | null>(null);
  const visible = games.filter(game => game.platforms.includes(consoleId) && (filter === 'all' || game.filters.includes(filter)));
  const close = () => { dialog.current?.close(); setDetail(null); opener.current?.focus(); };
  function Cover({ game, dialog = false }: { game: Game; dialog?: boolean }) { return cover(game) ? <img className={dialog ? 'dialog-cover' : undefined} src={cover(game)} alt={`${t('Обкладинка', 'Обложка')} ${game.title}`} width={720} height={720} loading={dialog ? 'eager' : 'lazy'} decoding="async" /> : <div className="missing-cover"><PlayStationController size={42} weight="light" /><span>{t('Обкладинку додамо', 'Обложку добавим')}</span></div>; }
  const genre = (game: Game) => language === 'ru' ? game.genreRu || 'Игра для PlayStation' : game.genre;
  const cover = (game: Game) => (game as Game & { imageUrl?: string }).imageUrl || (game.image ? imageUrl(game.image) : '');
  useEffect(() => { if (detail) dialog.current?.showModal(); }, [detail]);
  useEffect(() => {
    if (!detail) return;
    const resize = () => setViewportWidth(window.innerWidth);
    window.addEventListener('resize', resize);
    return () => window.removeEventListener('resize', resize);
  }, [detail]);
  useEffect(() => { track.current?.scrollTo({ left: 0 }); }, [filter, consoleId]);
  const scroll = (direction: number) => track.current?.scrollBy({ left: direction * (track.current.clientWidth * 0.7), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  return <section className="section games-section" id="games"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t('ІГРИ ДО ТВОЄЇ КОНСОЛІ', 'ИГРЫ ДЛЯ ТВОЕЙ КОНСОЛИ')}</p><h2>{t('У що зіграємо?', 'Во что сыграем?')}</h2></div><div className="carousel-controls"><span>{t('Гортай та обирай', 'Листай и выбирай')}</span><button className="icon-button" onClick={() => scroll(-1)} aria-label={t('Попередні ігри', 'Предыдущие игры')}><ArrowLeft size={20} /></button><button className="icon-button" onClick={() => scroll(1)} aria-label={t('Наступні ігри', 'Следующие игры')}><ArrowRight size={20} /></button></div></Reveal>
    <div className="game-filters" role="group" aria-label={t('Категорії ігор', 'Категории игр')}>{filters.map(([value, label]) => <button key={value} type="button" className={filter === value ? 'active' : ''} aria-pressed={filter === value} onClick={() => setFilter(value)}>{label}</button>)}</div>
    <div className="game-track" ref={track} role="region" aria-label={t('Добірка популярних ігор', 'Подборка популярных игр')} tabIndex={0}>
      {visible.map(game => <article className="game-card" key={game.id}><button type="button" className={`game-art${selected.includes(game.id) ? ' is-selected' : ''}`} aria-label={`${t('Детальніше про', 'Подробнее об игре')} ${game.title}`} onClick={event => { opener.current = event.currentTarget; setCoverWidth(event.currentTarget.clientWidth); setViewportWidth(window.innerWidth); setDetail(game); }}>
        <Cover game={game} />
        <span className="game-open-icon">{selected.includes(game.id) ? <Check size={19} /> : <ArrowUpRight size={19} />}</span>
      </button><div className="game-card-caption"><div><h3>{game.title}</h3><span>{genre(game)}</span></div><span className="game-players" aria-label={`${game.players} ${t('локальних гравців', 'локальных игроков')}`}><Users size={15} />{game.players}</span></div></article>)}
      {visible.length === 0 && <p className="empty-state">{t('Для цієї консолі немає ігор у вибраній категорії. Спробуй інший фільтр.', 'Для этой консоли нет игр в выбранной категории. Попробуй другой фильтр.')}</p>}
    </div><p className="section-footnote">{t('Додай бажані ігри до заявки. Наявність та видання підтвердимо перед орендою.', 'Добавь желаемые игры в заявку. Наличие и издание подтвердим перед арендой.')}</p>
  </div>
  {detail && <dialog ref={dialog} className={`game-dialog${viewportWidth < coverWidth + 372 ? " is-stacked" : ""}`} style={{ "--cover-width": `${coverWidth}px` } as CSSProperties} aria-labelledby="game-dialog-title" onCancel={event => { event.preventDefault(); close(); }} onClick={event => { if (event.target === event.currentTarget) close(); }}><div className="game-dialog-inner"><button className="icon-button dialog-close" onClick={close} aria-label={t('Закрити опис гри', 'Закрыть описание игры')}><X size={22} /></button><Cover game={detail} dialog /><div className="dialog-copy"><p className="eyebrow">{genre(detail)} · {detail.rating}</p><h2 id="game-dialog-title">{detail.title}</h2><p>{language === 'ru' ? detail.descriptionRu || 'Описание и условия доступа к игре уточним перед арендой.' : detail.description}</p><div className="game-meta"><span>{detail.platforms.map(platform => platform.toUpperCase()).join(' / ')}</span><span><Users size={17} />{detail.players === 1 ? t('1 гравець', '1 игрок') : `${t('До', 'До')} ${detail.players} ${t('гравців', 'игроков')}`}</span></div><button className="button button-light" onClick={() => { onToggle(detail.id); close(); }}>{selected.includes(detail.id) ? t('Прибрати із заявки', 'Убрать из заявки') : t('Додати до заявки', 'Добавить в заявку')}{selected.includes(detail.id) ? <Check size={18} /> : <Plus size={18} />}</button></div></div></dialog>}
  </section>;
}
