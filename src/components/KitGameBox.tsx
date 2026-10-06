import { imageUrl } from '../lib/api';
import { useI18n } from '../lib/i18n';
import '../kit-game-box.css';

export function KitGameBox({ game }: { game: 'fc27' | 'ufc6' }) {
  const { t } = useI18n();
  const title = game === 'fc27' ? 'FC 27' : 'UFC 6';
  return <picture>
    <source media="(min-width: 901px)" srcSet={`${imageUrl(`kit-${game}-case-640`)} 640w`} sizes="(max-width: 1100px) 21vw, 285px" />
    <img className={`kit-game-box kit-game-box-${game}`}
    src={imageUrl(`kit-${game}-case-320`)}
    srcSet={`${imageUrl(`kit-${game}-case-320`)} 320w, ${imageUrl(`kit-${game}-case-640`)} 640w`}
    sizes="(max-width: 700px) 33vw, (max-width: 1100px) 21vw, 275px"
    width={640} height={game === 'fc27' ? 950 : 903}
    alt={t(`Коробка гри EA SPORTS ${title} для PlayStation 5`, `Коробка игры EA SPORTS ${title} для PlayStation 5`)}
    loading="lazy" decoding="async" />
  </picture>;
}
