import { useEffect, useRef, useState } from 'react';
import { X } from '@phosphor-icons/react';
import { Header } from './sections/Header';
import { Hero } from './sections/Hero';
import { Tariffs } from './sections/Tariffs';
import { Booking } from './sections/Booking';
import { Games } from './sections/Games';
import { Kit, HowItWorks } from './sections/Story';
import { Footer } from './sections/Footer';
import { boot, fallbackCatalog, getCatalog, imageUrl } from './lib/api';
import type { CatalogStatus } from './lib/api';
import { reconcileGameIds, safeDays, type ConsoleId } from './lib/rental';
import { clearRentalDraft, emptyRentalDraft, readRentalDraft, saveRentalDraft, type RentalDraft } from './lib/draft';
import { useI18n } from './lib/i18n';
import './booking.css';

export default function App() {
  const [store, setStore] = useState(fallbackCatalog);
  const [draft, setDraft] = useState<RentalDraft>(() => readRentalDraft() ?? emptyRentalDraft());
  const { consoleId, days, gameIds } = draft;
  const draftRef = useRef(draft);
  const draftSaving = useRef(true);
  draftRef.current = draft;
  const [catalogStatus, setCatalogStatus] = useState<CatalogStatus>('loading');
  const [retry, setRetry] = useState(0);
  const [selectionChanged, setSelectionChanged] = useState(false);
  const [gameLimitReached, setGameLimitReached] = useState(false);
  const [legal, setLegal] = useState<'privacy' | 'terms' | null>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const legalOpener = useRef<HTMLElement | null>(null);
  const { t, language } = useI18n();
  useEffect(() => {
    let active = true;
    setCatalogStatus('loading');
    getCatalog().then(data => {
      if (!active) return;
      const current = draftRef.current;
      const limit = Math.min(100, data.settings.maxGames ?? 100, data.games.length);
      const reconciled = reconcileGameIds(current.gameIds, data.games, current.consoleId, limit);
      const nextDays = safeDays(current.consoleId, current.days, data.tariffs);
      if (reconciled.length !== current.gameIds.length || (nextDays !== null && nextDays !== current.days)) setSelectionChanged(true);
      setDraft(value => ({ ...value, days: safeDays(value.consoleId, value.days, data.tariffs) ?? value.days, gameIds: reconcileGameIds(value.gameIds, data.games, value.consoleId, limit), method: data.settings.pickup ? value.method : 'delivery' }));
      setStore(data); setCatalogStatus('ready');
    }).catch(() => { if (active) setCatalogStatus('error'); });
    return () => { active = false; };
  }, [retry]);
  useEffect(() => { if (draftSaving.current) saveRentalDraft(draft); }, [draft]);
  useEffect(() => { const save = () => { if (draftSaving.current) saveRentalDraft(draftRef.current); }; window.addEventListener('pagehide', save); return () => window.removeEventListener('pagehide', save); }, []);
  useEffect(() => { const favicon = document.createElement('link'); favicon.rel = 'icon'; favicon.type = 'image/webp'; favicon.href = imageUrl('favicon'); document.head.append(favicon); return () => { favicon.remove(); }; }, []);
  useEffect(() => { if (legal) dialog.current?.showModal(); }, [legal]);
  function updateDraft(patch: Partial<RentalDraft>) { draftSaving.current = true; setDraft(current => ({ ...current, ...patch })); }
  function submitted() { draftSaving.current = false; clearRentalDraft(); }
  function selectConsole(value: ConsoleId) { setDraft(current => ({ ...current, consoleId: value, days: safeDays(value, current.days, store.tariffs) ?? current.days, gameIds: catalogStatus === 'ready' ? reconcileGameIds(current.gameIds, store.games, value) : current.gameIds })); setGameLimitReached(false); }
  function choose(days: number) { updateDraft({ days }); document.getElementById('booking')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); }
  const maxGames = Math.max(0, Math.min(100, store.settings.maxGames ?? 100, store.games.length));
  function toggleGame(id: string) {
    if (catalogStatus !== 'ready' || !store.games.some(game => game.id === id && game.platforms.includes(consoleId))) return;
    if (!gameIds.includes(id) && gameIds.length >= maxGames) { setGameLimitReached(true); return; }
    setDraft(current => ({ ...current, gameIds: current.gameIds.includes(id) ? current.gameIds.filter(game => game !== id) : [...current.gameIds, id] }));
    setGameLimitReached(false);
  }
  function showLegal(page: 'privacy' | 'terms') { legalOpener.current = document.activeElement as HTMLElement; setLegal(page); }
  function closeLegal() { dialog.current?.close(); setLegal(null); legalOpener.current?.focus(); }
  const legalUrl = legal === 'privacy' ? language === 'ru' ? boot.privacyRuUrl : boot.privacyUrl : language === 'ru' ? boot.termsRuUrl : boot.termsUrl;
  const heroConsole: ConsoleId = store.tariffs.ps5.length ? 'ps5' : 'ps4';
  const heroTariff = store.tariffs[heroConsole][0];
  const selectedTariff = store.tariffs[consoleId].find(item => item.days === days);
  const secondFree = store.settings.baseControllers >= 2 || store.settings.extraControllerFee === 0;
  return <><a className="skip-link" href="#main-content">{t('Перейти до вмісту', 'Перейти к содержимому')}</a><Header /><main id="main-content"><Hero onChoosePS5={() => selectConsole(heroConsole)} consoleId={heroConsole} minimumDays={heroTariff?.days} price={heroTariff?.price ?? null} /><Tariffs freeDeliveryFrom={store.settings.freeDeliveryFrom} consoleId={consoleId} tariffs={store.tariffs[consoleId]} onConsole={selectConsole} onChoose={choose} /><Games maxGames={maxGames} games={store.games} consoleId={consoleId} selected={gameIds} onToggle={toggleGame} catalogStatus={catalogStatus} /><Kit secondFree={secondFree} /><HowItWorks settings={store.settings} /><Booking store={store} draft={draft} catalogStatus={catalogStatus} onRetry={() => setRetry(current => current + 1)} selectionChanged={selectionChanged} gameLimitReached={gameLimitReached} onDraftChange={updateDraft} onConsole={selectConsole} onToggleGame={toggleGame} onSubmitted={submitted} onLegal={showLegal} /></main><Footer consoleId={consoleId} days={days} price={selectedTariff?.price ?? null} settings={store.settings} onLegal={showLegal} />
    {legal && <dialog ref={dialog} className="legal-dialog" aria-labelledby="legal-dialog-title" onCancel={event => { event.preventDefault(); closeLegal(); }} onClick={event => { if (event.target === event.currentTarget) closeLegal(); }}><div><button className="icon-button dialog-close" aria-label={t('Закрити', 'Закрыть')} onClick={closeLegal}><X size={22} /></button><p className="eyebrow">JOYRENT</p><h2 id="legal-dialog-title">{legal === 'terms' ? t('Умови оренди', 'Условия аренды') : t('Конфіденційність', 'Конфиденциальность')}</h2>{legal === 'terms' ? <><p>{t('Бронювання на сайті не потребує оплати. Ми перевіримо доступність консолі та ігор і зв’яжемося з тобою для підтвердження.', 'Бронирование на сайте не требует оплаты. Мы проверим доступность консоли и игр и свяжемся с тобой для подтверждения.')}</p><p>{t('До підтвердження узгодимо комплектацію, дати й час отримання та повернення, доставку, оформлення із заставою або за договором та відповідальність за обладнання.', 'До подтверждения согласуем комплектацию, даты и время получения и возврата, доставку, оформление с залогом или по договору и ответственность за оборудование.')}</p><p>{t('Продовження можливе після перевірки доступності. Не розбирай обладнання та одразу повідомляй про несправності.', 'Продление возможно после проверки доступности. Не разбирай оборудование и сразу сообщай о неисправностях.')}</p></> : <><p>{t('Ім’я, телефон, необов’язковий Telegram, адреса доставки, вибір оренди та побажання щодо ігор потрібні для обробки бронювання. Не надсилай через сайт пароль PSN, документи чи платіжні дані.', 'Имя, телефон, необязательный Telegram, адрес доставки, выбор аренды и пожелания об играх нужны для обработки брони. Не отправляй через сайт пароль PSN, документы или платёжные данные.')}</p><p>{t('Дані бронювання зберігаються у магазині JOYRENT. Доступ мають уповноважені працівники. Щоб уточнити або видалити дані, напиши на info@joyrent.online.', 'Данные бронирования хранятся в магазине JOYRENT. Доступ имеют уполномоченные сотрудники. Чтобы уточнить или удалить данные, напиши на info@joyrent.online.')}</p><p>{t('Форма не підписує тебе на рекламну розсилку.', 'Форма не подписывает тебя на рекламную рассылку.')}</p></>}{legalUrl && <a className="text-link" href={legalUrl}>{t('Повна інформація', 'Полная информация')}</a>}<button className="button button-outline" onClick={closeLegal}>{t('Зрозуміло', 'Понятно')}</button></div></dialog>}
  </>;
}
