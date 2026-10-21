import { useEffect, useRef, useState, type CSSProperties } from 'react';
import { Check, Plus, Users, X } from '@phosphor-icons/react';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { imageUrl } from '../lib/api';
import { localPlayers, type ConsoleId, type Game } from '../lib/rental';
import { resolveGameImageSources } from '../lib/game-presentation';
import { useI18n } from '../lib/i18n';
import { hasKnownInternetRequirement, localPlayerLabel } from '../lib/copy';
import { loopDialogTab } from '../lib/dialog-focus';

export function gameImageSources(game: Game) {
  return resolveGameImageSources(game,imageUrl);
}

export function GameCover({ game, full = false, sizes = '(max-width: 700px) 84vw, (max-width: 1200px) 30vw, 320px' }: { game: Game; full?: boolean; sizes?: string }) {
  const { t } = useI18n();
  const root = useRef<HTMLSpanElement>(null);
  const [nearby, setNearby] = useState(full);
  const sources = gameImageSources(game);
  useEffect(() => {
    if (full || nearby || !root.current) return;
    if (!('IntersectionObserver' in window)) { setNearby(true); return; }
    const observer = new IntersectionObserver(entries => { if (entries.some(entry => entry.isIntersecting)) { setNearby(true); observer.disconnect(); } }, { rootMargin: '240px' });
    observer.observe(root.current);
    return () => observer.disconnect();
  }, [full, nearby]);
  return <span ref={root} className={full ? 'game-cover-wrapper full-cover' : 'game-cover-wrapper'}>{sources.src ? nearby && <img className={full ? 'dialog-cover' : undefined} src={full ? sources.src : sources.srcSet ? imageUrl(`${game.image}-360`) : sources.src} srcSet={full ? undefined : sources.srcSet} sizes={full ? undefined : sizes} alt={`${t('Обкладинка', 'Обложка')} ${game.title}`} width={720} height={720} loading={full ? 'eager' : 'lazy'} decoding="async" /> : <span className="missing-cover"><PlayStationController size={42} weight="light" /><span>{t('Обкладинку додамо', 'Обложку добавим')}</span></span>}</span>;
}

export function GameFilters({ value, onChange }: { value: string; onChange: (filter: string) => void }) {
  const { t } = useI18n();
  const filters = [['all', t('Усі', 'Все')], ['two', t('На двох', 'Вдвоём')], ['party', t('Для компанії', 'Для компании')], ['racing', t('Перегони', 'Гонки')], ['sport', t('Спорт', 'Спорт')], ['story', t('Сюжетні', 'Сюжетные')], ['kids', t('Дітям', 'Детям')]];
  return <div className="game-filters" role="group" aria-label={t('Категорії ігор', 'Категории игр')}>{filters.map(([filter, label]) => <button key={filter} type="button" className={value === filter ? 'active' : ''} aria-pressed={value === filter} onClick={() => onChange(filter)}>{label}</button>)}</div>;
}

export function GameDetailDialog({ game, consoleId, selected, coverWidth = 320, disabled = false, disabledMessage, onToggle, onClose }: { game: Game; consoleId: ConsoleId; selected: boolean; coverWidth?: number; disabled?: boolean; disabledMessage?: string; onToggle: () => void; onClose: () => void }) {
  const { t, language } = useI18n();
  const dialog = useRef<HTMLDialogElement>(null);
  const [width, setWidth] = useState(window.innerWidth);
  const titleId = `game-dialog-title-${game.id}`;
  const players = localPlayers(game, consoleId);
  const description = language === 'ru' ? game.descriptionRu || 'Описание и условия доступа к игре уточним перед арендой.' : game.description;
  useEffect(() => { dialog.current?.showModal(); const resize = () => setWidth(window.innerWidth); window.addEventListener('resize', resize); return () => { window.removeEventListener('resize', resize); dialog.current?.close(); }; }, []);
  const close = () => { dialog.current?.close(); onClose(); };
  return <dialog ref={dialog} className={`game-dialog${width < coverWidth + 372 ? ' is-stacked' : ''}`} style={{ '--cover-width': `${coverWidth}px` } as CSSProperties} aria-labelledby={titleId} onKeyDown={loopDialogTab} onCancel={event => { event.preventDefault(); close(); }} onClick={event => { if (event.target === event.currentTarget) close(); }}><div className="game-dialog-inner"><button type="button" className="icon-button dialog-close" autoFocus onClick={close} aria-label={t('Закрити опис гри', 'Закрыть описание игры')}><X size={22} /></button><GameCover game={game} full /><div className="dialog-copy"><p className="eyebrow">{language === 'ru' ? game.genreRu || 'Игра для PlayStation' : game.genre} · {game.rating}</p><h2 id={titleId}>{game.title}</h2><p>{description}</p>{game.requiresInternet && !hasKnownInternetRequirement(description, language) && <p className="game-internet-note">{t('Потрібен інтернет. Доступ до мережевих режимів уточнимо.', 'Нужен интернет. Доступ к сетевым режимам уточним.')}</p>}<div className="game-meta"><span>{consoleId.toUpperCase()}</span><span><Users size={17} />{players === 1 ? localPlayerLabel(players, language) : `${t('До', 'До')} ${players} ${t('локальних гравців', 'локальных игроков')}`}</span></div><>{disabledMessage && <p className="game-selection-notice" role="status">{disabledMessage}</p>}<button type="button" className="button button-light" disabled={disabled} onClick={() => { onToggle(); close(); }}>{selected ? t('Прибрати з бронювання', 'Убрать из брони') : t('Додати до бронювання', 'Добавить в бронь')}{selected ? <Check size={18} /> : <Plus size={18} />}</button></></div></div></dialog>;
}
