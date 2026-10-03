import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ArrowUpRight, ArrowRight, CheckCircle, ShieldCheck, X, CaretLeft } from '@phosphor-icons/react';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { GamePicker } from '../components/GamePicker';
import { addRentalDays, dayLabel, deliveryCharge, isIntentionalSubmission, money, todayInKyiv, validateStartDate, type ConsoleId } from '../lib/rental';
import { submitRequest, type CatalogStatus, type StoreCatalog, type RequestReceipt } from '../lib/api';
import type { RentalDraft } from '../lib/draft';
import { useI18n } from '../lib/i18n';

function requestId() { return globalThis.crypto.randomUUID?.() ?? Array.from(globalThis.crypto.getRandomValues(new Uint8Array(16))).map(value => value.toString(16).padStart(2, '0')).join(''); }

export function Booking({ store, draft, catalogStatus, onRetry, selectionChanged, gameLimitReached, onDraftChange, onConsole, onToggleGame, onSubmitted, onLegal }: {
  store: StoreCatalog; draft: RentalDraft; catalogStatus: CatalogStatus; onRetry: () => void; selectionChanged: boolean; gameLimitReached: boolean;
  onDraftChange: (patch: Partial<RentalDraft>) => void; onConsole: (id: ConsoleId) => void; onToggleGame: (id: string) => void; onSubmitted: () => void; onLegal: (page: 'privacy' | 'terms') => void;
}) {
  const { t, language } = useI18n();
  const { consoleId, days, gameIds, start, controllers, method, step } = draft;
  const today = todayInKyiv();
  const lastDate = new Date(`${today}T12:00:00Z`);
  lastDate.setUTCFullYear(lastDate.getUTCFullYear() + 1);
  const maxStart = lastDate.toISOString().slice(0, 10);
  // Contact fields are intentionally kept in component memory only.
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [consent, setConsent] = useState(false);
  const [website, setWebsite] = useState('');
  const [error, setError] = useState('');
  const [pickerOpen, setPickerOpen] = useState(false);
  const [sending, setSending] = useState(false);
  const [receipt, setReceipt] = useState<(RequestReceipt & { consoleId: ConsoleId; days: number; start: string; end: string }) | null>(null);
  const firstInput = useRef<HTMLInputElement>(null);
  const formHeading = useRef<HTMLDivElement>(null);
  const choices = useRef<HTMLDivElement>(null);
  const pickerOpener = useRef<HTMLButtonElement>(null);
  const attempt = useRef({ fingerprint: '', id: requestId() });
  const tariff = store.tariffs[consoleId].find(item => item.days === days);
  const validDate = validateStartDate(start, today) && start <= maxStart;
  const end = validDate && tariff ? addRentalDays(start, tariff.days) : '';
  const deposit = consoleId === 'ps5' ? store.settings.depositPs5 : store.settings.depositPs4;
  const extraCount = Math.max(0, controllers - store.settings.baseControllers);
  const extraPrice = extraCount ? store.settings.extraControllerFee : 0;
  const secondFree = store.settings.baseControllers >= 2 || store.settings.extraControllerFee === 0;
  const deliveryPrice = deliveryCharge(method, days, store.settings.freeDeliveryFrom, store.settings.deliveryFee);
  const includedDelivery = method === 'delivery' && days >= store.settings.freeDeliveryFrom;
  const knownSubtotal = tariff ? tariff.price + (extraPrice ?? 0) + (deliveryPrice ?? 0) : null;
  const maxGames = Math.max(0, Math.min(100, store.settings.maxGames ?? 100, store.games.length));
  const selectedGames = store.games.filter(game => gameIds.includes(game.id) && game.platforms.includes(consoleId));
  const catalogReady = catalogStatus === 'ready';
  useEffect(() => { setError(''); }, [language]);
  useEffect(() => { if (step === 1 && !receipt) { formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }); firstInput.current?.focus({ preventScroll: true }); } }, [step, receipt]);
  function back() { onDraftChange({ step: 0 }); requestAnimationFrame(() => { choices.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }); choices.current?.querySelector('button')?.focus({ preventScroll: true }); }); }
  function closePicker() { setPickerOpen(false); requestAnimationFrame(() => pickerOpener.current?.focus({ preventScroll: true })); }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const submitter = (event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null;
    if (!isIntentionalSubmission(step, submitter?.dataset.intent) || sending) return;
    if (!catalogReady || !tariff || !store.acceptingRequests) { setError(t('Зараз заявки недоступні. Перевір каталог і спробуй ще раз.', 'Сейчас заявки недоступны. Проверь каталог и попробуй ещё раз.')); return; }
    if (!validDate) { setError(t('Оберіть коректну дату отримання.', 'Выбери корректную дату получения.')); back(); return; }
    if (!event.currentTarget.reportValidity()) return;
    const base = { console: consoleId, days, startDate: start, controllers, gameIds, name, phone, method, address, consent, website };
    const fingerprint = JSON.stringify(base);
    if (attempt.current.fingerprint && attempt.current.fingerprint !== fingerprint) attempt.current.id = requestId();
    attempt.current.fingerprint = fingerprint;
    setSending(true); setError('');
    try {
      const result = await submitRequest({ ...base, language, requestId: attempt.current.id });
      onSubmitted();
      setReceipt({ ...result, consoleId, days, start, end });
      requestAnimationFrame(() => formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center' }));
    } catch (cause) { setError(cause instanceof Error ? cause.message : t('Не вдалося надіслати заявку.', 'Не удалось отправить заявку.')); }
    finally { setSending(false); }
  }
  return <section className="section booking-section" id="booking"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t('ЗАЯВКА НА ОРЕНДУ', 'ЗАЯВКА НА АРЕНДУ')}</p><h2>{t('Розрахуй оренду.', 'Рассчитай аренду.')}</h2></div><p className="heading-side-copy">{t('Обери дату отримання. Ціну за термін покажемо одразу.', 'Выбери дату получения. Стоимость за весь срок покажем сразу.')}</p></Reveal>
    {!receipt && <div className="catalog-status" aria-live="polite">{catalogStatus === 'loading' && <p role="status">{t('Завантажуємо актуальні тарифи та ігри…', 'Загружаем актуальные тарифы и игры…')}</p>}{catalogStatus === 'error' && <div role="alert"><p>{t('Не вдалося завантажити каталог. Твій вибір збережено.', 'Не удалось загрузить каталог. Твой выбор сохранён.')}</p><button type="button" className="button button-outline" onClick={onRetry}>{t('Повторити', 'Повторить')}</button></div>}{catalogReady && !store.acceptingRequests && <p role="status">{t('Магазин зараз не приймає заявки. Вибір можна зберегти й повернутися пізніше.', 'Магазин сейчас не принимает заявки. Выбор можно сохранить и вернуться позже.')}</p>}{catalogReady && selectionChanged && <p role="status">{t('Каталог оновлено: недоступні ігри прибрано, обраний термін звірено з тарифами.', 'Каталог обновлён: недоступные игры убраны, выбранный срок сверен с тарифами.')}</p>}</div>}
    {receipt ? <div className="booking-success" ref={formHeading} role="status"><CheckCircle size={52} weight="light" /><p className="eyebrow">{t('ЗАЯВКА ', 'ЗАЯВКА ')}{receipt.reference}</p><h3>{t('Заявку отримано.', 'Заявка получена.')}</h3><p>{t('Ми зв’яжемося з тобою, перевіримо доступність консолі та узгодимо доставку, комплектацію і заставу.', 'Мы свяжемся с тобой, проверим доступность консоли и согласуем доставку, комплектацию и залог.')}</p><div className="success-recap"><span>{receipt.consoleId.toUpperCase()} · {dayLabel(receipt.days, language)}</span><strong>{money(receipt.rentalAmount)} грн</strong><span>{receipt.start.split('-').reverse().join('.')} — {receipt.end.split('-').reverse().join('.')}</span></div><p className="muted small">{t('Заявка очікує підтвердження. Оплата ще не потрібна.', 'Заявка ожидает подтверждения. Оплата пока не нужна.')}</p><button type="button" className="button button-outline" onClick={() => { setReceipt(null); onDraftChange({ step: 0 }); setConsent(false); attempt.current = { fingerprint: '', id: requestId() }; }}>{t('Нова заявка ', 'Новая заявка ')}<ArrowUpRight size={18} /></button></div> : <form className={`booking-layout${step === 1 ? ' booking-contact-step' : ''}`} noValidate onSubmit={submit}>
      <div className="booking-fields"><div className="rental-choices" ref={choices}>
        <div className="booking-row row-top"><span className="field-label">{t('Консоль', 'Консоль')}</span><ConsoleSwitch value={consoleId} onChange={onConsole} id="booking" /></div>
        <fieldset className="term-field"><legend className="field-label">{t('Термін оренди', 'Срок аренды')}</legend><div className="term-options">{store.tariffs[consoleId].map(item => <button type="button" key={item.days} aria-pressed={days === item.days} className={days === item.days ? 'selected' : ''} onClick={() => onDraftChange({ days: item.days })}>{dayLabel(item.days, language)}</button>)}</div>{store.tariffs[consoleId].length === 0 && <p className="input-help" role="status">{t('Тарифи цієї консолі недоступні. Обери іншу консоль.', 'Тарифы этой консоли недоступны. Выбери другую консоль.')}</p>}</fieldset>
        <div className="date-fields"><label><span className="field-label">{t('Отримання', 'Получение')}</span><input type="date" aria-label={t('Дата отримання', 'Дата получения')} min={today} max={maxStart} value={start} onChange={event => onDraftChange({ start: event.target.value })} required aria-invalid={!validDate} /></label><label><span className="field-label">{t('Повернення', 'Возврат')}</span><input type="date" aria-label={t('Дата повернення', 'Дата возврата')} value={end} readOnly /><span className="input-help">{t('Розраховано за обраним терміном', 'Рассчитано по выбранному сроку')}</span></label></div>
        {!validDate && <p className="field-error">{t('Оберіть сьогоднішню або майбутню дату протягом року.', 'Выбери сегодняшнюю или будущую дату в течение года.')}</p>}
        <div className="controller-row"><div><span className="field-label">{t('Геймпади', 'Геймпады')}</span><span className="input-help">{secondFree ? t('Другий без доплати', 'Второй без доплаты') : t('Для гри разом', 'Для совместной игры')}</span></div><div className="controller-options">{[1, 2].map(value => <button type="button" key={value} className={controllers === value ? 'selected' : ''} aria-pressed={controllers === value} onClick={() => onDraftChange({ controllers: value })}><PlayStationController size={21} weight="light" />{value}</button>)}</div></div>
        <div className="booking-games"><div className="booking-row"><span className="field-label">{t('Ігри за бажанням', 'Игры по желанию')}</span><button ref={pickerOpener} type="button" className="text-link" disabled={!catalogReady} onClick={() => setPickerOpen(true)}>{t('Обрати ігри ', 'Выбрать игры ')}<ArrowUpRight size={15} /></button></div>{selectedGames.length ? <div className="selected-games">{selectedGames.map(game => <button type="button" className="selected-game" key={game.id} aria-label={`${t('Прибрати', 'Убрать')} ${game.title}`} onClick={() => onToggleGame(game.id)}>{game.title}<X size={14} /></button>)}</div> : <p className="muted small">{t('Обери тут або підкажемо після заявки.', 'Выбери здесь или подскажем после заявки.')}</p>}{gameLimitReached && <p role="status" className="input-help">{t('До заявки можна додати до', 'В заявку можно добавить до')} {maxGames} {t('ігор.', 'игр.')}</p>}</div>
      </div>
      {step === 1 && <><div className="contact-recap"><div><strong>{consoleId.toUpperCase()} · {dayLabel(days, language)} · {controllers} {t('геймпад(и)', 'геймпад(а)')}</strong><span>{start.split('-').reverse().join('.')} — {end ? end.split('-').reverse().join('.') : '—'}</span>{selectedGames.length > 0 && <span className="contact-recap-games">{selectedGames.map(game => game.title).join(' · ')}</span>}</div><button type="button" className="text-link" onClick={back}>{t('Змінити', 'Изменить')}</button></div><div className="customer-fields" ref={formHeading}><div className="customer-heading"><h3>{t('Як з тобою зв’язатися?', 'Как с тобой связаться?')}</h3><button type="button" className="text-link" onClick={back}><CaretLeft size={15} /> {t('Назад', 'Назад')}</button></div><div className="customer-inputs"><label><span className="field-label">{t('Ім’я', 'Имя')}</span><input ref={firstInput} name="name" autoComplete="given-name" placeholder={t('Твоє ім’я', 'Твоё имя')} value={name} onChange={event => setName(event.target.value)} minLength={2} maxLength={100} required /></label><label><span className="field-label">{t('Телефон', 'Телефон')}</span><input type="tel" name="phone" autoComplete="tel" placeholder="+380 __ ___ __ __" value={phone} onChange={event => setPhone(event.target.value)} minLength={10} maxLength={24} required /></label></div>{store.settings.pickup && <fieldset className="delivery-method"><legend className="field-label">{t('Отримання', 'Получение')}</legend><label><input type="radio" name="method" checked={method === 'delivery'} onChange={() => onDraftChange({ method: 'delivery' })} /> {t('Доставка', 'Доставка')}</label><label><input type="radio" name="method" checked={method === 'pickup'} onChange={() => onDraftChange({ method: 'pickup' })} /> {t('Самовивіз', 'Самовывоз')}</label></fieldset>}{method === 'delivery' && <label className="address-field"><span className="field-label">{t('Місто та адреса', 'Город и адрес')}</span><input name="address" autoComplete="street-address" placeholder={t('Місто, вулиця, будинок', 'Город, улица, дом')} value={address} onChange={event => setAddress(event.target.value)} minLength={5} maxLength={300} required /><span className="input-help">{t('Перевіримо зону доставки та узгодимо час.', 'Проверим зону доставки и согласуем время.')}</span></label>}<label className="consent"><input type="checkbox" checked={consent} onChange={event => setConsent(event.target.checked)} required /><span>{t('Погоджуюся з ', 'Соглашаюсь с ')}<button type="button" onClick={() => onLegal('terms')}>{t('умовами оренди', 'условиями аренды')}</button> {t('та ', 'и ')}<button type="button" onClick={() => onLegal('privacy')}>{t('обробкою персональних даних', 'обработкой персональных данных')}</button>.</span></label><label className="honeypot" aria-hidden="true">{t('Ваш сайт', 'Ваш сайт')}<input name="website" value={website} onChange={event => setWebsite(event.target.value)} autoComplete="off" tabIndex={-1} /></label></div></>}
      </div>
      <aside className="booking-summary"><p className="eyebrow">{t('ТВОЯ ОРЕНДА', 'ТВОЯ АРЕНДА')}</p><h3>{consoleId === 'ps5' ? 'PlayStation 5' : 'PlayStation 4'}</h3><div className="summary-price" aria-live="polite">{tariff ? <><strong>{money(tariff.price)}</strong><span>грн / {dayLabel(days, language)}</span></> : <span>{t('Тариф недоступний', 'Тариф недоступен')}</span>}</div>{tariff && <><dl className="summary-lines">{controllers === 2 && <div><dt>{t('Другий геймпад', 'Второй геймпад')}</dt><dd>{secondFree ? t('Без доплати', 'Без доплаты') : extraPrice === null ? t('Узгодимо', 'Согласуем') : `${money(extraPrice)} грн`}</dd></div>}<div><dt>{method === 'pickup' ? t('Самовивіз', 'Самовывоз') : t('Доставка', 'Доставка')}{includedDelivery && <span className="muted">{t('у зеленій / жовтій зоні', 'в зелёной / жёлтой зоне')}</span>}</dt><dd>{includedDelivery ? t('Включено', 'Включено') : deliveryPrice === null ? t('За адресою', 'По адресу') : `${money(deliveryPrice)} грн`}</dd></div><div><dt>{t('Застава ', 'Залог ')}<span className="muted">{t('(повертається)', '(возвращается)')}</span></dt><dd>{deposit === null ? t('Узгодимо', 'Согласуем') : `${money(deposit)} грн`}</dd></div></dl><div className="summary-subtotal"><span>{t('Попередня сума', 'Предварительная сумма')}</span><strong>{money(knownSubtotal!)} грн</strong></div></>}<p className="summary-note">{t('Вартість доставки за адресою та наявність підтвердимо особисто.', 'Стоимость доставки по адресу и наличие подтвердим лично.')}</p>{error && <p role="alert" className="form-error">{error}</p>}
        <div hidden={step !== 0}><button key="continue" type="button" data-intent="continue" className="button button-light summary-button" disabled={!validDate || !tariff || !catalogReady} onClick={event => { event.preventDefault(); if (catalogReady && tariff && validDate) onDraftChange({ step: 1 }); }}>{t('Продовжити ', 'Продолжить ')}<ArrowRight size={20} /></button></div>
        <div hidden={step !== 1}><button key="submit" type="submit" data-intent="submit" className="button button-light summary-button" disabled={step !== 1 || sending || !catalogReady || !tariff || !store.acceptingRequests}>{sending ? t('Надсилаємо…', 'Отправляем…') : t('Надіслати заявку', 'Отправить заявку')}{!sending && <ArrowUpRight size={20} />}</button><p className="summary-trust"><ShieldCheck size={17} weight="light" />{t('Без оплати до підтвердження', 'Без оплаты до подтверждения')}</p></div>
      </aside>
    </form>}
  </div>{pickerOpen && <GamePicker games={store.games} consoleId={consoleId} selected={gameIds} maxGames={maxGames} onToggle={onToggleGame} onClose={closePicker} />}</section>;
}
