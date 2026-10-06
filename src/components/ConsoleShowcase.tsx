import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AnimatePresence, motion, useIsPresent } from 'framer-motion';
import { ArrowLeft, ArrowRight, Check, Plus, Sparkle } from '@phosphor-icons/react';
import { dailyRate, dayLabel, money, type ConsoleId, type Tariff } from '../lib/rental';
import { boot, imageUrl, type CatalogStatus } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { useMotionPreference } from '../lib/motion';
import { useNearbyMedia } from '../lib/use-nearby-media';
import '../console-showcase.css';

export function ConsoleShowcase({ consoleId, tariffs, secondFree, catalogStatus, onConsole, onChoose }: {
  consoleId: ConsoleId; tariffs: Tariff[]; secondFree: boolean; catalogStatus: CatalogStatus;
  onConsole: (value: ConsoleId) => void; onChoose: (days: number) => void;
}) {
  const { t, language } = useI18n();
  const reduced = useMotionPreference();
  const stage = useRef<HTMLDivElement>(null);
  const nearby = useNearbyMedia(stage);
  const [visible, setVisible] = useState(false);
  const [documentVisible, setDocumentVisible] = useState(() => !document.hidden);
  useEffect(() => {
    const observer = 'IntersectionObserver' in window ? new IntersectionObserver(([entry]) => setVisible(entry.isIntersecting), { threshold: .1 }) : null;
    if (stage.current) observer?.observe(stage.current);
    const update = () => setDocumentVisible(!document.hidden);
    document.addEventListener('visibilitychange', update);
    return () => { observer?.disconnect(); document.removeEventListener('visibilitychange', update); };
  }, []);
  const firstTariff = tariffs[0];
  const rate = catalogStatus === 'ready' && firstTariff ? dailyRate(firstTariff.price, firstTariff.days) : null;
  const nextConsole = consoleId === 'ps5' ? 'ps4' : 'ps5';
  return <div ref={stage} className="console-showcase" data-motion={!reduced && visible && documentVisible ? 'running' : 'paused'}>
    <div className="console-feature-frame">
      <AnimatePresence mode="wait" initial={false}>
        <ConsoleFeature key={consoleId} consoleId={consoleId} reduced={reduced}>{present => <>
          <div className="console-feature-copy"><p className="eyebrow">{t('ТВОЯ КОНСОЛЬ НА ВЕЧІР', 'ТВОЯ КОНСОЛЬ НА ВЕЧЕР')}</p><h3 id={`console-title-${consoleId}`}>PlayStation {consoleId === 'ps5' ? '5' : '4'}<span className="brand-dot">.</span></h3><p className="console-feature-description">{consoleId === 'ps5' ? t('Нові світи, великі пригоди та матчі з друзями.', 'Новые миры, большие приключения и матчи с друзьями.') : t('Улюблені хіти, затишні вечори та гра удвох.', 'Любимые хиты, уютные вечера и игра вдвоём.')}</p><ul className="console-feature-list"><li><Check size={18} aria-hidden="true" />{t('Консоль, кабелі та геймпади', 'Консоль, кабели и геймпады')}</li><li><Check size={18} aria-hidden="true" />{secondFree ? t('Другий геймпад без доплати', 'Второй геймпад без доплаты') : t('Обери 1 або 2 геймпади', 'Выбери 1 или 2 геймпада')}</li><li><Check size={18} aria-hidden="true" />{t('Ігри з каталогу та твої побажання', 'Игры из каталога и твои пожелания')}</li></ul></div>
          <div className="console-feature-media" aria-hidden="true">{nearby && <div className="console-photo-float"><img src={consoleId === 'ps5' ? `${boot.assetBase}/images/ps5-commercial-mobile-560.webp` : imageUrl('ps4-studio')} srcSet={consoleId === 'ps5' ? `${boot.assetBase}/images/ps5-commercial-mobile-560.webp 560w, ${boot.assetBase}/images/ps5-commercial-mobile.webp 1120w, ${boot.assetBase}/images/ps5-commercial-mobile-1448.webp 1448w` : undefined} sizes="(max-width: 700px) calc(100vw - 48px), (max-width: 1100px) 46vw, 37vw" alt="" width={consoleId === 'ps5' ? 1120 : 1586} height={consoleId === 'ps5' ? 840 : 992} loading="lazy" decoding="async" /></div>}</div>
          <div className="console-feature-details"><div className="console-subscriptions"><p>{t('Підписки включено', 'Подписки включены')}</p><div className="console-subscription-badges"><span className="subscription-badge subscription-deluxe"><Plus size={20} weight="bold" aria-hidden="true" /><span>PS Plus Deluxe</span></span><span className="subscription-badge subscription-ea"><Sparkle size={20} aria-hidden="true" /><span>EA Play</span></span></div></div>
            <div className="console-price-block">{rate && firstTariff ? <><p className="console-rate">{t('від', 'от')} <strong>{rate.approximate && <span aria-hidden="true">≈</span>}{money(rate.amount)}</strong><span>грн/день</span></p><p className="console-minimum">{t('При оренді на', 'При аренде на')} {dayLabel(firstTariff.days, language)} · {money(firstTariff.price)} грн {t('за весь термін', 'за весь срок')}</p></> : <p className="console-catalog-status" role="status">{catalogStatus === 'loading' ? t('Завантажуємо тарифи…', 'Загружаем тарифы…') : t('Тарифи зараз недоступні', 'Тарифы сейчас недоступны')}</p>}</div>
            <button type="button" className="button button-light console-book" disabled={!present || !rate || !firstTariff} onClick={() => firstTariff && onChoose(firstTariff.days)}>{t('Обрати', 'Выбрать')} {consoleId.toUpperCase()}<ArrowRight size={19} aria-hidden="true" /></button>
          </div>
        </>}</ConsoleFeature>
      </AnimatePresence>
    </div>
    <div className="console-showcase-footer"><p className="console-subscription-note">{t('EA Play та PS Plus Deluxe на нашому акаунті входять у вартість оренди PS5 і PS4.', 'EA Play и PS Plus Deluxe на нашем аккаунте входят в стоимость аренды PS5 и PS4.')}</p><div className="console-showcase-controls"><button type="button" className="icon-button console-showcase-previous" aria-label={`${t('Попередня консоль', 'Предыдущая консоль')}: ${nextConsole.toUpperCase()}`} onClick={() => onConsole(nextConsole)}><ArrowLeft size={19} aria-hidden="true" /></button><span aria-live="polite" aria-atomic="true">{consoleId === 'ps5' ? '01' : '02'} <span>/ 02</span></span><button type="button" className="icon-button console-showcase-next" aria-label={`${t('Наступна консоль', 'Следующая консоль')}: ${nextConsole.toUpperCase()}`} onClick={() => onConsole(nextConsole)}><ArrowRight size={19} aria-hidden="true" /></button></div></div>
  </div>;
}

// An outgoing card keeps its old content during the fade; prevent it choosing a stale tariff.
function ConsoleFeature({ consoleId, reduced, children }: { consoleId: ConsoleId; reduced: boolean; children: (present: boolean) => ReactNode }) {
  const present = useIsPresent();
  return <motion.article className="console-feature" data-console={consoleId} inert={!present} aria-labelledby={`console-title-${consoleId}`} initial={reduced ? false : { opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={{ duration: reduced ? 0 : .22 }}>{children(present)}</motion.article>;
}
