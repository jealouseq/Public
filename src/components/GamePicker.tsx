import { useEffect, useId, useRef, useState } from 'react';
import { ArrowUpRight, CaretDown, Check, MagnifyingGlass, Plus, X } from '@phosphor-icons/react';
import { GameCover, GameDetailDialog, GameFilters } from './GamePresentation';
import { matchesGameFilter, type ConsoleId, type Game } from '../lib/rental';
import { matchesGameQuery } from '../lib/game-search';
import { useI18n } from '../lib/i18n';
import { gameLimitLabel } from '../lib/copy';
import '../game-picker.css';

export function GamePicker({ games, consoleId, selected, maxGames, requestedGame, onRequestedGameChange, onToggle, onClose }: {
  games: Game[]; consoleId: ConsoleId; selected: string[]; maxGames: number; requestedGame: string;
  onRequestedGameChange: (value: string) => void; onToggle: (id: string) => void; onClose: () => void;
}) {
  const { t, language } = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const searchInput = useRef<HTMLInputElement>(null);
  const requestInput = useRef<HTMLInputElement>(null);
  const detailOpener = useRef<HTMLElement | null>(null);
  const [filter, setFilter] = useState('all');
  const [query, setQuery] = useState('');
  const [requestOpen, setRequestOpen] = useState(Boolean(requestedGame));
  const [detail, setDetail] = useState<Game | null>(null);
  const [coverWidth, setCoverWidth] = useState(280);
  const id = useId();
  const trimmedQuery = query.trim();
  const visible = games.filter(game => matchesGameFilter(game, consoleId, filter) && matchesGameQuery(game.title, query));
  useEffect(() => { dialog.current?.showModal(); return () => dialog.current?.close(); }, []);
  useEffect(() => {
    const viewport = window.visualViewport;
    const viewportEvents = viewport ?? window;
    const updateViewport = () => {
      const height = viewport?.height ?? window.innerHeight;
      dialog.current?.style.setProperty('--game-picker-viewport-height', `${height}px`);
      dialog.current?.style.setProperty('--game-picker-viewport-top', `${viewport?.offsetTop ?? 0}px`);
      if (dialog.current) dialog.current.dataset.compactViewport = height <= 360 ? 'true' : 'false';
      requestAnimationFrame(() => requestAnimationFrame(() => {
        if (document.activeElement === requestInput.current) requestInput.current?.scrollIntoView({ block: 'center', behavior: 'instant' });
      }));
    };
    updateViewport();
    viewportEvents.addEventListener('resize', updateViewport);
    viewportEvents.addEventListener('scroll', updateViewport);
    return () => { viewportEvents.removeEventListener('resize', updateViewport); viewportEvents.removeEventListener('scroll', updateViewport); };
  }, []);
  const close = () => { dialog.current?.close(); onClose(); };
  const closeDetail = () => { setDetail(null); requestAnimationFrame(() => detailOpener.current?.focus({ preventScroll: true })); };
  const revealRequest = (prefill?: string) => {
    if (prefill?.trim()) onRequestedGameChange(Array.from(prefill.trim()).slice(0, 120).join(''));
    setRequestOpen(true);
    requestAnimationFrame(() => {
      requestInput.current?.focus({ preventScroll: true });
      requestInput.current?.scrollIntoView({ block: 'center', behavior: 'instant' });
    });
  };
  return <>
    <dialog ref={dialog} className="game-picker-dialog game-picker-structured" aria-labelledby={`${id}-title`} onCancel={event => { event.preventDefault(); close(); }} onClick={event => { if (event.target === event.currentTarget) close(); }}>
      <div className="game-picker-inner">
        <div className="game-picker-content">
        <div className="game-picker-toolbar">
          <div className="game-picker-heading"><div><p className="eyebrow">{consoleId.toUpperCase()}</p><h2 id={`${id}-title`}>{t('Обери бажані ігри.', 'Выбери желаемые игры.')}</h2></div><button type="button" className="icon-button" autoFocus aria-label={t('Закрити добірку ігор', 'Закрыть подборку игр')} onClick={close}><X size={22} /></button></div>
          <div className="game-picker-search">
            <label className="sr-only" htmlFor={`${id}-search`}>{t('Пошук гри', 'Поиск игры')}</label>
            <MagnifyingGlass size={20} aria-hidden="true" />
            <input ref={searchInput} id={`${id}-search`} type="search" value={query} onChange={event => setQuery(event.target.value)} placeholder={t('Знайди свою гру', 'Найди свою игру')} autoComplete="off" enterKeyHint="search" />
            {query && <button type="button" className="icon-button" aria-label={t('Очистити пошук', 'Очистить поиск')} onClick={() => { setQuery(''); searchInput.current?.focus({ preventScroll: true }); }}><X size={18} /></button>}
          </div>
          <GameFilters value={filter} onChange={setFilter} />
        </div>
        <div className="game-picker-scroll" role="region" aria-label={t('Ігри та побажання до бронювання', 'Игры и пожелания к брони')} tabIndex={0}>
          <div className="game-picker-result" role="status" aria-live="polite">{trimmedQuery && `${t('Знайдено', 'Найдено')}: ${visible.length}`}</div>
          {visible.length > 0 ? <div className="game-picker-grid">
            {visible.map(game => <article className="picker-game" key={game.id}>
              <button type="button" className="game-art" aria-label={`${t('Детальніше про', 'Подробнее об игре')} ${game.title}`} onClick={event => { detailOpener.current = event.currentTarget; setCoverWidth(event.currentTarget.clientWidth); setDetail(game); }}><GameCover game={game} sizes="(max-width: 700px) 40vw, 240px" /><span className="game-open-icon"><ArrowUpRight size={18} /></span></button>
              <h3>{game.title}</h3>
              <button type="button" className={`picker-game-toggle${selected.includes(game.id) ? ' selected' : ''}`} aria-pressed={selected.includes(game.id)} aria-label={`${selected.includes(game.id) ? t('Прибрати з бронювання', 'Убрать из брони') : t('Додати до бронювання', 'Добавить в бронь')} ${game.title}`} disabled={!selected.includes(game.id) && selected.length >= maxGames} onClick={() => onToggle(game.id)}>{selected.includes(game.id) ? <Check size={17} /> : <Plus size={17} />}{selected.includes(game.id) ? t('Обрано', 'Выбрано') : t('Додати', 'Добавить')}</button>
            </article>)}
          </div> : <div className="game-picker-empty"><p>{filter !== 'all' ? t('У цій категорії збігів немає.', 'В этой категории совпадений нет.') : t('За цим запитом ігор не знайдено.', 'По этому запросу игр не найдено.')}</p>{filter !== 'all' && <div className="game-picker-empty-actions"><button type="button" className="button button-outline" onClick={() => setFilter('all')}>{t('Усі категорії', 'Все категории')}</button></div>}</div>}
          {selected.length >= maxGames && maxGames < games.filter(game => game.platforms.includes(consoleId)).length && <p className="input-help game-picker-limit" role="status">{gameLimitLabel(maxGames, language)}</p>}
          <section className="game-picker-request" data-request-open={requestOpen} aria-labelledby={`${id}-request-title`}>
            <div className="missing-game-heading"><div><h3 id={`${id}-request-title`}>{t('Не знайшли гру?', 'Не нашли игру?')}</h3>{!requestOpen && requestedGame.trim() && <p className="missing-game-saved">{t('Побажання:', 'Пожелание:')} <strong>{requestedGame.trim()}</strong></p>}</div><button type="button" className="missing-game-toggle" aria-expanded={requestOpen} aria-controls={`${id}-request`} onClick={() => requestOpen ? setRequestOpen(false) : revealRequest(visible.length === 0 ? trimmedQuery : undefined)}><span>{requestOpen ? t('Згорнути', 'Свернуть') : requestedGame.trim() ? t('Змінити назву', 'Изменить название') : t('Вказати назву', 'Указать название')}</span><CaretDown size={17} aria-hidden="true" /></button></div>
            <div id={`${id}-request`} className="missing-game-fields" hidden={!requestOpen}><label htmlFor={`${id}-game`} className="field-label">{t('Назва гри', 'Название игры')}</label><input ref={requestInput} id={`${id}-game`} type="text" value={requestedGame} onChange={event => onRequestedGameChange(event.target.value)} maxLength={120} autoComplete="off" enterKeyHint="done" aria-describedby={`${id}-request-help`} placeholder={t('Наприклад, Minecraft', 'Например, Minecraft')} /><p id={`${id}-request-help`}>{t('Наявність і можливість додати гру підтвердимо після бронювання.', 'Наличие и возможность добавить игру подтвердим после оформления брони.')}</p></div>
          </section>
        </div>
        </div>
        <div className="game-picker-footer"><p aria-live="polite">{t('Обрано', 'Выбрано')}: {selected.length}{requestedGame.trim() && <span>{t(' + побажання', ' + пожелание')}</span>}</p><button type="button" className="button button-light" onClick={close}>{t('Готово', 'Готово')} <Check size={18} /></button></div>
      </div>
    </dialog>
    {detail && <GameDetailDialog game={detail} consoleId={consoleId} selected={selected.includes(detail.id)} coverWidth={coverWidth} disabled={!selected.includes(detail.id) && selected.length >= maxGames} disabledMessage={!selected.includes(detail.id) && selected.length >= maxGames ? t('Досягнуто ліміту ігор. Прибери одну, щоб додати іншу.', 'Достигнут лимит игр. Убери одну, чтобы добавить другую.') : undefined} onToggle={() => onToggle(detail.id)} onClose={closeDetail} />}
  </>;
}
