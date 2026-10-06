import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { PlayStationMark } from './PlayStationMark';
import '../kit-game-box.css';

export function KitGameBox({ game }: { game: 'fc27' | 'ufc6' }) {
  const { t } = useI18n();
  const title = game === 'fc27' ? 'FC 27' : 'UFC 6';

  return <div className={`kit-game-box kit-game-box-${game}`} role="img" aria-label={t(`Коробка гри EA SPORTS ${title} для PlayStation 5`, `Коробка игры EA SPORTS ${title} для PlayStation 5`)}>
    <div className="kit-box-shell" aria-hidden="true">
      <span className="kit-box-spine" />
      <div className="kit-box-front">
        <div className="kit-box-inlay">
          <div className="kit-box-platform"><PlayStationMark size={18} /><span>PS5</span></div>
          <img className="kit-box-cover" src={imageUrl(`cover-${game}-360`)} srcSet={`${imageUrl(`cover-${game}-360`)} 360w, ${imageUrl(`cover-${game}-720`)} 720w`} sizes="(max-width: 700px) 120px, (max-width: 1100px) 150px, 190px" alt="" width={720} height={720} loading="lazy" decoding="async" />
          <div className="kit-box-print-footer"><span>EA SPORTS</span><strong>{title}</strong></div>
        </div>
        <span className="kit-box-laminate" />
        <span className="kit-box-edge" />
      </div>
    </div>
  </div>;
}
