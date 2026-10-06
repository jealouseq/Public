import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import '../kit-game-disc.css';

export function KitGameDisc({ game }: { game: 'fc27' | 'ufc6' }) {
  const { t } = useI18n();
  const title = game === 'fc27' ? 'FC 27' : 'UFC 6';
  return <div className={`kit-game-disc kit-game-disc-${game}`} role="img" aria-label={t(`Ігровий диск EA SPORTS ${title}`, `Игровой диск EA SPORTS ${title}`)}>
    <div className="kit-disc-surface" aria-hidden="true">
      <div className="kit-disc-print">
        <img className="kit-disc-cover" src={imageUrl(`cover-${game}-360`)} srcSet={`${imageUrl(`cover-${game}-360`)} 360w, ${imageUrl(`cover-${game}-720`)} 720w`} sizes="(max-width: 700px) 140px, 220px" alt="" width={720} height={720} loading="lazy" decoding="async" />
        <span className="kit-disc-title"><span>EA SPORTS</span><strong>{title}</strong></span>
      </div>
      <span className="kit-disc-laminate" />
      <span className="kit-disc-hub" />
    </div>
  </div>;
}
