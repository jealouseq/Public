import { useId, type Ref } from 'react';
import { Plus, X } from '@phosphor-icons/react';
import { PlayStationMark } from './PlayStationMark';
import type { Game } from '../lib/rental';
import { useI18n } from '../lib/i18n';
import './booking-games.css';

export function BookingGames({ games, requestedGame, ready, openerRef, onOpen, onRemove, onRemoveRequest }: {
  games: Game[]; requestedGame: string; ready: boolean; openerRef: Ref<HTMLButtonElement>;
  onOpen: () => void; onRemove: (id: string) => void; onRemoveRequest: () => void;
}) {
  const { t } = useI18n();
  const titleId = useId();
  const hasSelection = games.length > 0 || Boolean(requestedGame.trim());
  return <section className="booking-games" aria-labelledby={titleId}>
    <div className="booking-games-heading">
      <div className="booking-games-intro">
        <span className="booking-games-icon" aria-hidden="true"><PlayStationMark size={28} /></span>
        <div><h3 id={titleId}>{t('Ігри за бажанням', 'Игры по желанию')}</h3><p>{t('Обери з каталогу або вкажи назву своєї гри.', 'Выбери из каталога или укажи название своей игры.')}</p></div>
      </div>
      <button ref={openerRef} type="button" className="game-picker-button" aria-haspopup="dialog" disabled={!ready} onClick={onOpen}>
        {hasSelection ? t('Змінити ігри', 'Изменить игры') : t('Обрати ігри', 'Выбрать игры')}
        {games.length > 0 && <span className="game-picker-count" aria-label={t(`Обрано: ${games.length}`, `Выбрано: ${games.length}`)}>{games.length}</span>}
        <Plus size={18} aria-hidden="true" />
      </button>
    </div>
    {games.length > 0 && <ul className="selected-games" aria-label={t('Обрані ігри', 'Выбранные игры')}>{games.map(game => <li key={game.id}><button type="button" className="selected-game" aria-label={`${t('Прибрати', 'Убрать')} ${game.title}`} onClick={() => onRemove(game.id)}><span>{game.title}</span><X size={16} aria-hidden="true" /></button></li>)}</ul>}
    {requestedGame.trim() && <div className="requested-game-note"><p><span>{t('Інша гра', 'Другая игра')}</span><strong>{requestedGame.trim()}</strong></p><button type="button" aria-label={t('Прибрати побажання', 'Убрать пожелание')} onClick={onRemoveRequest}><X size={18} aria-hidden="true" /></button></div>}
  </section>;
}
