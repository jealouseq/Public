import { useEffect, useId, useRef, useState } from 'react';
import { ArrowUpRight, CaretDown, Check, MagnifyingGlass, Plus, X } from '@phosphor-icons/react';
import { GameCover, GameDetailDialog, GameFilters } from './GamePresentation';
import { matchesGameFilter, type ConsoleId, type Game } from '../lib/rental';
import { matchesGameQuery } from '../lib/game-search';
import { useI18n } from '../lib/i18n';
import { gameLimitLabel } from '../lib/copy';

export function GamePicker({ games, consoleId, selected, maxGames, requestedGame, onRequestedGameChange, onToggle, onClose }: {
  games: Game[]; consoleId: ConsoleId; selected: string[]; maxGames: number; requestedGame: string;
  onRequestedGameChange: (value: string) => void; onToggle: (id: string) => void; onClose: () => void;
}) {
  const { t, language } = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const searchInput = useRef<HTMLInputElement>(null);
  const detailOpener = useRef<HTMLElement | null>(null);
  const [filter, setFilter] = useState('all');
  const [query, setQuery] = useState('');
  const [requestOpen, setRequestOpen] = useState(Boolean(requestedGame));
  const [detail, setDetail] = useState<Game | null>(null);
  const [coverWidth, setCoverWidth] = useState(280);
  const id = useId();
  const visible = games.filter(game => matchesGameFilter(game, consoleId, filter) && matchesGameQuery(game.title, query));
  useEffect(() => { dialog.current?.showModal(); return () => dialog.current?.close(); }, []);
  const close = () => { dialog.current?.close(); onClose(); };
  const closeDetail = () => { setDetail(null); requestAnimationFrame(() => detailOpener.current?.focus({ preventScroll: true })); };
  return <>
    <dialog ref={dialog} className="game-picker-dialog" aria-labelledby={`${id}-title`} onCancel={event => { event.preventDefault(); close(); }} onClick={event => { if (event.target === event.currentTarget) close(); }}>
      <div className="game-picker-inner">
        <div className="game-picker-heading"><div><p className="eyebrow">{consoleId.toUpperCase()}</p><h2 id={`${id}-title`}>{t('Обери бажані ігри.', 'Выбери желаемые игры.')}</h2></div><button type="button" className="icon-button" autoFocus aria-label={t('Закрити добірку ігор', 'Закрыть подборку игр')} onClick={close}><X size={22} /></button></div>
        <div className="game-picker-search">
          <label className="sr-only" htmlFor={`${id}-search`}>{t('Пошук гри', 'Поиск игры')}</label>
          <MagnifyingGlass size={20} aria-hidden="true" />
          <input ref={searchInput} id={`${id}-search`} type="search" value={query} onChange={event => setQuery(event.target.value)} placeholder={t('Знайди свою гру', 'Найди свою игру')} autoComplete="off" enterKeyHint="search" />
          {query && <button type="button" className="icon-button" aria-label={t('Очистити пошук', 'Очистить поиск')} onClick={() => { setQuery(''); searchInput.current?.focus({ preventScroll: true }); }}><X size={18} /></button>}
        </div>
        <GameFilters value={filter} onChange={setFilter} />
        <div className="game-picker-request">
          <button type="button" className="missing-game-toggle" aria-expanded={requestOpen} aria-controls={`${id}-request`} onClick={() => setRequestOpen(value => !value)}><span>{t('Не знайшли гру у списку?', 'Не нашли игру в списке?')}</span><CaretDown size={17} aria-hidden="true" /></button>
          {requestOpen && <div id={`${id}-request`} className="missing-game-fields"><label htmlFor={`${id}-game`} className="field-label">{t('Назва гри', 'Название игры')}</label><input id={`${id}-game`} type="text" value={requestedGame} onChange={event => onRequestedGameChange(event.target.value)} maxLength={120} autoComplete="off" enterKeyHint="done" aria-describedby={`${id}-request-help`} placeholder={t('Яку гру хочеш?', 'Какую игру хочешь?')} /><p id={`${id}-request-help`}>{t('Перевіримо можливість додати її після заявки.', 'Проверим возможность добавить её после заявки.')}</p></div>}
        </div>
        <div className="game-picker-result" role="status" aria-live="polite">{query && `${t('Знайдено', 'Найдено')}: ${visible.length}`}</div>
        <div className="game-picker-grid">
          {visible.map(game => <article className="picker-game" key={game.id}>
            <button type="button" className="game-art" aria-label={`${t('Детальніше про', 'Подробнее об игре')} ${game.title}`} onClick={event => { detailOpener.current = event.currentTarget; setCoverWidth(event.currentTarget.clientWidth); setDetail(game); }}><GameCover game={game} sizes="(max-width: 700px) 40vw, 240px" /><span className="game-open-icon"><ArrowUpRight size={18} /></span></button>
            <h3>{game.title}</h3>
            <button type="button" className={`picker-game-toggle${selected.includes(game.id) ? ' selected' : ''}`} aria-pressed={selected.includes(game.id)} aria-label={`${selected.includes(game.id) ? t('Прибрати із заявки', 'Убрать из заявки') : t('Додати до заявки', 'Добавить в заявку')} ${game.title}`} disabled={!selected.includes(game.id) && selected.length >= maxGames} onClick={() => onToggle(game.id)}>{selected.includes(game.id) ? <Check size={17} /> : <Plus size={17} />}{selected.includes(game.id) ? t('Обрано', 'Выбрано') : t('Додати', 'Добавить')}</button>
          </article>)}
        </div>
        {visible.length === 0 && <p className="empty-state">{query ? t('Такої гри у списку немає. Залиш назву вище — перевіримо можливість додати.', 'Такой игры в списке нет. Оставь название выше — проверим возможность добавить.') : t('У цій категорії немає ігор.', 'В этой категории нет игр.')}</p>}
        {selected.length >= maxGames && maxGames < games.filter(game => game.platforms.includes(consoleId)).length && <p className="input-help" role="status">{gameLimitLabel(maxGames, language)}</p>}
        <div className="game-picker-footer"><p aria-live="polite">{t('Обрано', 'Выбрано')}: {selected.length}{requestedGame.trim() && <span>{t(' + побажання', ' + пожелание')}</span>}</p><button type="button" className="button button-light" onClick={close}>{t('Готово', 'Готово')} <Check size={18} /></button></div>
      </div>
    </dialog>
    {detail && <GameDetailDialog game={detail} consoleId={consoleId} selected={selected.includes(detail.id)} coverWidth={coverWidth} disabled={!selected.includes(detail.id) && selected.length >= maxGames} disabledMessage={!selected.includes(detail.id) && selected.length >= maxGames ? t('Досягнуто ліміту ігор. Прибери одну, щоб додати іншу.', 'Достигнут лимит игр. Убери одну, чтобы добавить другую.') : undefined} onToggle={() => onToggle(detail.id)} onClose={closeDetail} />}
  </>;
}
