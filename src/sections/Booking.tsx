import { useEffect, useId, useRef, useState, type FormEvent } from 'react';
import { ArrowUpRight, ArrowRight, CheckCircle, ShieldCheck, CaretLeft } from '@phosphor-icons/react';
import { PlayStationController } from '../../components/ui/playstation-controller';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { GamePicker } from '../components/GamePicker';
import { OdesaAddressInput } from '../components/OdesaAddressInput';
import { SecurityChoice } from '../components/SecurityChoice';
import { BookingGames } from '../components/BookingGames';
import { BookingConsent } from '../components/BookingConsent';
import { DailyPrice } from '../components/DailyPrice';
import { addRentalDays, dayLabel, deliveryCharge, isIntentionalSubmission, money, todayInKyiv, validateStartDate, type ConsoleId } from '../lib/rental';
import { submitRequest, type CatalogStatus, type StoreCatalog, type RequestReceipt } from '../lib/api';
import type { RentalDraft } from '../lib/draft';
import { useI18n } from '../lib/i18n';
import { controllerLabel, gameLimitLabel } from '../lib/copy';
import { canonicalRentalIntent, isPlainRentalText, isUkrainianPhone, normalizeTelegramContact } from '../lib/booking-input';

function requestId() { return globalThis.crypto.randomUUID?.() ?? Array.from(globalThis.crypto.getRandomValues(new Uint8Array(16))).map(value => value.toString(16).padStart(2, '0')).join(''); }

export function Booking({ store, draft, catalogStatus, onRetry, selectionChanged, gameLimitReached, onDraftChange, onConsole, onToggleGame, onSubmitted, onLegal }: {
  store: StoreCatalog; draft: RentalDraft; catalogStatus: CatalogStatus; onRetry: () => void; selectionChanged: boolean; gameLimitReached: boolean;
  onDraftChange: (patch: Partial<RentalDraft>) => void; onConsole: (id: ConsoleId) => void; onToggleGame: (id: string) => void; onSubmitted: () => void; onLegal: (page: 'privacy' | 'terms') => void;
}) {
  const { t, language } = useI18n();
  const { consoleId, days, gameIds, start, controllers, method, securityMode, step } = draft;
  const today = todayInKyiv();
  const lastDate = new Date(`${today}T12:00:00Z`);
  lastDate.setUTCFullYear(lastDate.getUTCFullYear() + 1);
  const maxStart = lastDate.toISOString().slice(0, 10);
  // Contact fields are intentionally kept in component memory only.
  const [name, setName] = useState('');
  const [nameError, setNameError] = useState(false);
  const nameErrorId = useId();
  const [phone, setPhone] = useState('');
  const [telegram, setTelegram] = useState('');
  const [telegramError, setTelegramError] = useState(false);
  const telegramHelpId = useId();
  const telegramErrorId = useId();
  const telegramInput = useRef<HTMLInputElement>(null);
  const [address, setAddress] = useState('');
  const [addressError, setAddressError] = useState(false);
  const [requestedGame, setRequestedGame] = useState('');
  const [consent, setConsent] = useState(false);
  const [website, setWebsite] = useState('');
  const [error, setError] = useState('');
  const [phoneError, setPhoneError] = useState(false);
  const phoneErrorId = useId();
  const [pickerOpen, setPickerOpen] = useState(false);
  const [sending, setSending] = useState(false);
  const [receipt, setReceipt] = useState<(RequestReceipt & { consoleId: ConsoleId; days: number; start: string; end: string }) | null>(null);
  const firstInput = useRef<HTMLInputElement>(null);
  const phoneInput = useRef<HTMLInputElement>(null);
  const addressInput = useRef<HTMLInputElement>(null);
  const formHeading = useRef<HTMLDivElement>(null);
  const choices = useRef<HTMLDivElement>(null);
  const pickerOpener = useRef<HTMLButtonElement>(null);
  const errorSummary = useRef<HTMLParagraphElement>(null);
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
  const rentalSubtotal = tariff ? tariff.price + (extraPrice ?? 0) : null;
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
    if (!catalogReady || !tariff || !store.acceptingRequests) { setError(t('Бронювання наразі недоступне. Перевір каталог і спробуй ще раз.', 'Бронирование сейчас недоступно. Проверь каталог и попробуй ещё раз.')); return; }
    if (!validDate) { setError(t('Обери коректну дату отримання.', 'Выбери корректную дату получения.')); back(); return; }
    if (!isPlainRentalText(name, 2, 100)) {
      setError(''); setNameError(true);
      firstInput.current?.focus();
      return;
    }
    setNameError(false);
    if (!isUkrainianPhone(phone)) {
      setError(''); setPhoneError(true);
      phoneInput.current?.focus();
      return;
    }
    setPhoneError(false);
    const telegramContact = normalizeTelegramContact(telegram);
    if (telegramContact === null) {
      setError(''); setTelegramError(true);
      telegramInput.current?.focus();
      return;
    }
    setTelegramError(false);
    if (method === 'delivery' && !isPlainRentalText(address, 5, 300)) {
      setError(''); setAddressError(true);
      addressInput.current?.focus();
      return;
    }
    setAddressError(false);
    if (!isPlainRentalText(requestedGame, 0, 120)) { setError(''); setPickerOpen(true); return; }
    if (!event.currentTarget.reportValidity()) return;
    const base = { console: consoleId, days, startDate: start, controllers, gameIds, name, phone, ...(telegramContact ? { telegram: telegramContact } : {}), method, address, securityMode, requestedGame: requestedGame.trim(), consent, website };
    const canonical = canonicalRentalIntent(base);
    const fingerprint = JSON.stringify(canonical);
    if (attempt.current.fingerprint && attempt.current.fingerprint !== fingerprint) attempt.current.id = requestId();
    attempt.current.fingerprint = fingerprint;
    setSending(true); setError('');
    try {
      const result = await submitRequest({ ...base, ...canonical, language, requestId: attempt.current.id });
      onSubmitted();
      setReceipt({ ...result, consoleId, days, start, end });
      requestAnimationFrame(() => formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center' }));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('Не вдалося оформити бронювання.', 'Не удалось оформить бронь.'));
      requestAnimationFrame(() => errorSummary.current?.focus());
    }
    finally { setSending(false); }
  }
  return <section className="section booking-section" id="booking"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t('БРОНЮВАННЯ', 'БРОНИРОВАНИЕ')}</p><h2>{t('Розрахуй вартість.', 'Рассчитай стоимость.')}</h2></div><p className="heading-side-copy">{t('Обери консоль, термін і дату отримання — вартість оренди покажемо одразу.', 'Выбери консоль, срок и дату получения — стоимость аренды покажем сразу.')}</p></Reveal>
    {!receipt && <div className="catalog-status" aria-live="polite">{catalogStatus === 'loading' && <p role="status">{t('Завантажуємо актуальні тарифи та ігри…', 'Загружаем актуальные тарифы и игры…')}</p>}{catalogStatus === 'error' && <div role="alert"><p>{t('Не вдалося завантажити каталог. Твій вибір збережено.', 'Не удалось загрузить каталог. Твой выбор сохранён.')}</p><button type="button" className="button button-outline" onClick={onRetry}>{t('Повторити', 'Повторить')}</button></div>}{catalogReady && !store.acceptingRequests && <p role="status">{t('Бронювання наразі недоступне. Спробуй пізніше.', 'Бронирование сейчас недоступно. Попробуй позже.')}</p>}{catalogReady && selectionChanged && <p role="status">{t('Деякі ігри або обраний термін недоступні. Перевір вибір для бронювання.', 'Некоторые игры или выбранный срок недоступны. Проверь параметры брони.')}</p>}</div>}
    {receipt ? <div className="booking-success" ref={formHeading} role="status"><CheckCircle size={52} weight="light" /><p className="eyebrow">{t('БРОНЮВАННЯ ', 'БРОНЬ ')}{receipt.reference}</p><h3>{t('Бронювання отримано.', 'Бронь получена.')}</h3><p>{t('Ми зв’яжемося з тобою, підтвердимо наявність консолі та узгодимо доставку й умови оформлення.', 'Мы свяжемся с тобой, подтвердим наличие консоли и согласуем доставку и условия оформления.')}</p><div className="success-recap"><span>{receipt.consoleId.toUpperCase()} · {dayLabel(receipt.days, language)}</span><strong>{money(receipt.rentalAmount)} грн</strong><span>{receipt.start.split('-').reverse().join('.')} — {receipt.end.split('-').reverse().join('.')}</span></div><p className="muted small">{t('Бронювання очікує підтвердження. Оплата ще не потрібна.', 'Бронь ожидает подтверждения. Оплата пока не нужна.')}</p><button type="button" className="button button-outline" onClick={() => { setReceipt(null); setRequestedGame(''); onDraftChange({ step: 0 }); setConsent(false); attempt.current = { fingerprint: '', id: requestId() }; }}>{t('Нове бронювання ', 'Новая бронь ')}<ArrowUpRight size={18} /></button></div> : <form className={`booking-layout${step === 1 ? ' booking-contact-step' : ''}`} noValidate onKeyDown={event => { if (event.key === 'Enter' && event.target instanceof HTMLInputElement && event.target.closest('.customer-fields')) event.preventDefault(); }} onSubmit={submit}>
      <div className="booking-fields"><div className="rental-choices" ref={choices}>
        <div className="booking-row row-top"><span className="field-label">{t('Консоль', 'Консоль')}</span><ConsoleSwitch value={consoleId} onChange={onConsole} id="booking" /></div>
        <fieldset className="term-field"><legend className="field-label">{t('Термін оренди', 'Срок аренды')}</legend><div className="term-options">{store.tariffs[consoleId].map(item => <button type="button" key={item.days} aria-pressed={days === item.days} className={days === item.days ? 'selected' : ''} onClick={() => onDraftChange({ days: item.days })}>{dayLabel(item.days, language)}</button>)}</div>{store.tariffs[consoleId].length === 0 && <p className="input-help" role="status">{t('Тарифи цієї консолі недоступні. Обери іншу консоль.', 'Тарифы этой консоли недоступны. Выбери другую консоль.')}</p>}</fieldset>
        <div className="date-fields"><label><span className="field-label">{t('Отримання', 'Получение')}</span><input type="date" aria-label={t('Дата отримання', 'Дата получения')} min={today} max={maxStart} value={start} onChange={event => onDraftChange({ start: event.target.value })} required aria-invalid={!validDate} /></label><label><span className="field-label">{t('Повернення', 'Возврат')}</span><input type="date" aria-label={t('Дата повернення', 'Дата возврата')} value={end} readOnly /><span className="input-help">{t('Дату повернення розраховано автоматично.', 'Дата возврата рассчитана автоматически.')}</span></label></div>
        {!validDate && <p className="field-error">{t('Обери сьогоднішню або майбутню дату протягом року.', 'Выбери сегодняшнюю или будущую дату в течение года.')}</p>}
        <div className="controller-row"><div><span className="field-label">{t('Геймпади', 'Геймпады')}</span><span className="input-help">{secondFree ? t('Другий без доплати', 'Второй без доплаты') : t('Для гри разом', 'Для совместной игры')}</span></div><div className="controller-options">{[1, 2].map(value => <button type="button" key={value} className={controllers === value ? 'selected' : ''} aria-pressed={controllers === value} onClick={() => onDraftChange({ controllers: value })}><PlayStationController size={28} weight="light" />{value}</button>)}</div></div>
        <BookingGames games={selectedGames} requestedGame={requestedGame} ready={catalogReady} openerRef={pickerOpener} onOpen={() => setPickerOpen(true)} onRemove={onToggleGame} onRemoveRequest={() => setRequestedGame('')} />
        {gameLimitReached && <p role="status" className="input-help">{gameLimitLabel(maxGames, language)}</p>}
        <SecurityChoice value={securityMode} deposit={deposit} onChange={value => onDraftChange({ securityMode: value })} />
      </div>
      {step === 1 && <><div className="contact-recap"><div><strong>{consoleId.toUpperCase()} · {dayLabel(days, language)} · {controllerLabel(controllers, language)} · {securityMode === 'contract' ? t('за договором', 'по договору') : t('із заставою', 'с залогом')}</strong><span>{start.split('-').reverse().join('.')} — {end ? end.split('-').reverse().join('.') : '—'}</span>{selectedGames.length > 0 && <span className="contact-recap-games">{selectedGames.map(game => game.title).join(' · ')}</span>}{requestedGame.trim() && <span className="contact-recap-games">{t('Побажання:', 'Пожелание:')} {requestedGame.trim()}</span>}</div><button type="button" className="text-link" onClick={back}>{t('Змінити', 'Изменить')}</button></div><div className="customer-fields" ref={formHeading}><div className="customer-heading"><h3>{t('Як з тобою зв’язатися?', 'Как с тобой связаться?')}</h3><button type="button" className="text-link" onClick={back}><CaretLeft size={15} /> {t('Назад', 'Назад')}</button></div><div className="customer-inputs"><label><span className="field-label">{t('Ім’я', 'Имя')}</span><input ref={firstInput} name="name" autoComplete="given-name" enterKeyHint="next" onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); phoneInput.current?.focus(); } }} placeholder={t('Твоє ім’я', 'Твоё имя')} value={name} onChange={event => { setName(event.target.value); if (nameError && isPlainRentalText(event.target.value, 2, 100)) setNameError(false); }} aria-invalid={nameError || undefined} aria-describedby={nameError ? nameErrorId : undefined} minLength={2} maxLength={200} required />{nameError && <span id={nameErrorId} className="field-error phone-field-error" role="alert">{t('Вкажи ім’я звичайним текстом: 2–100 символів.', 'Укажи имя обычным текстом: 2–100 символов.')}</span>}</label><label><span className="field-label">{t('Телефон', 'Телефон')}</span><input ref={phoneInput} type="tel" inputMode="tel" name="phone" autoComplete="tel" enterKeyHint="next" onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); telegramInput.current?.focus(); } }} placeholder="+380 __ ___ __ __" value={phone} onChange={event => { setPhone(event.target.value); if (phoneError && isUkrainianPhone(event.target.value)) setPhoneError(false); }} aria-invalid={phoneError || undefined} aria-describedby={phoneError ? phoneErrorId : undefined} minLength={10} maxLength={24} required />{phoneError && <span id={phoneErrorId} className="field-error phone-field-error" role="alert">{t('Вкажи український номер телефону.', 'Укажи украинский номер телефона.')}</span>}</label><label className="telegram-contact"><span className="field-label">{t('Telegram (необов’язково)', 'Telegram (необязательно)')}</span><input ref={telegramInput} type="text" name="telegram" autoComplete="off" autoCapitalize="none" autoCorrect="off" spellCheck={false} enterKeyHint="next" onKeyDown={event => { if (event.key === 'Enter') { event.preventDefault(); if (method === 'delivery') addressInput.current?.focus(); else event.currentTarget.form?.querySelector<HTMLInputElement>('.consent input')?.focus(); } }} placeholder="@username" value={telegram} onChange={event => { setTelegram(event.target.value); if (telegramError && normalizeTelegramContact(event.target.value) !== null) setTelegramError(false); }} maxLength={80} aria-invalid={telegramError || undefined} aria-describedby={telegramError ? `${telegramHelpId} ${telegramErrorId}` : telegramHelpId} /><span id={telegramHelpId} className="input-help">{t('Нік або посилання https://t.me/username', 'Ник или ссылка https://t.me/username')}</span>{telegramError && <span id={telegramErrorId} className="field-error phone-field-error" role="alert">{t('Вкажи нік Telegram: 5–32 латинські літери, цифри або _.', 'Укажи ник Telegram: 5–32 латинские буквы, цифры или _.')}</span>}</label></div>{store.settings.pickup && <fieldset className="delivery-method"><legend className="field-label">{t('Отримання', 'Получение')}</legend><label><input type="radio" name="method" checked={method === 'delivery'} onChange={() => onDraftChange({ method: 'delivery' })} /> {t('Доставка', 'Доставка')}</label><label><input type="radio" name="method" checked={method === 'pickup'} onChange={() => onDraftChange({ method: 'pickup' })} /> {t('Самовивіз', 'Самовывоз')}</label></fieldset>}{method === 'delivery' && <div className="booking-address"><OdesaAddressInput inputRef={addressInput} value={address} onChange={value => { setAddress(value); if (addressError && isPlainRentalText(value, 5, 300)) setAddressError(false); }} error={addressError ? t('Вкажи адресу звичайним текстом: 5–300 символів.', 'Укажи адрес обычным текстом: 5–300 символов.') : undefined} /></div>}<BookingConsent checked={consent} onChange={setConsent} onLegal={onLegal} /><label className="honeypot" aria-hidden="true">{t('Ваш сайт', 'Ваш сайт')}<input name="website" value={website} onChange={event => setWebsite(event.target.value)} autoComplete="off" tabIndex={-1} /></label></div></>}
      </div>
      <div className="booking-summary"><p className="eyebrow">{t('ТВОЯ ОРЕНДА', 'ТВОЯ АРЕНДА')}</p><h3>{consoleId === 'ps5' ? 'PlayStation 5' : 'PlayStation 4'}</h3><div className="summary-price" aria-live="polite">{tariff ? <><strong>{money(tariff.price)}</strong><span>грн / {dayLabel(days, language)}</span></> : <span>{t('Тариф недоступний', 'Тариф недоступен')}</span>}</div>{tariff && <DailyPrice price={tariff.price} days={tariff.days} className="summary-daily" />}{tariff && <><dl className="summary-lines">{controllers === 2 && <div><dt>{t('Другий геймпад', 'Второй геймпад')}</dt><dd>{secondFree ? t('Без доплати', 'Без доплаты') : extraPrice === null ? t('Узгодимо', 'Согласуем') : `${money(extraPrice)} грн`}</dd></div>}<div><dt>{method === 'pickup' ? t('Самовивіз', 'Самовывоз') : t('Доставка', 'Доставка')}{includedDelivery && <span className="muted">{t('у зеленій / жовтій зоні', 'в зелёной / жёлтой зоне')}</span>}</dt><dd>{includedDelivery ? t('Включено', 'Включено') : deliveryPrice === null ? t('розрахуємо за адресою', 'рассчитаем по адресу') : `${money(deliveryPrice)} грн`}</dd></div>{securityMode === 'contract' ? <div className="summary-security"><dt>{t('Оформлення', 'Оформление')}</dt><dd>{t('За договором', 'По договору')}<span className="muted">{t('після перевірки документів', 'после проверки документов')}</span></dd></div> : <div className="summary-deposit"><dt>{t('Застава', 'Залог')}</dt><dd>{deposit === null ? t('Узгодимо', 'Согласуем') : <><span>{money(deposit)} грн</span><span className="muted"> / {t('повертається', 'возвращается')}</span></>}</dd></div>}</dl><div className="summary-subtotal"><span>{t('Вартість оренди', 'Стоимость аренды')}</span><strong>{money(rentalSubtotal!)} грн</strong></div></>}<p className="summary-note">{securityMode === 'contract' ? t('Умови договору, доставку та наявність підтвердимо після бронювання.', 'Условия договора, доставку и наличие подтвердим после оформления брони.') : t('Застава сплачується окремо. Доставку та наявність підтвердимо після бронювання.', 'Залог оплачивается отдельно. Доставку и наличие подтвердим после оформления брони.')}</p>{error && <p ref={errorSummary} tabIndex={-1} role="alert" className="form-error">{error}</p>}
        <div hidden={step !== 0}><button key="continue" type="button" data-intent="continue" className="button button-light summary-button" disabled={!validDate || !tariff || !catalogReady} onClick={event => { event.preventDefault(); if (catalogReady && tariff && validDate) onDraftChange({ step: 1 }); }}>{t('Продовжити бронювання', 'Продолжить бронирование')}<ArrowRight size={20} /></button></div>
        <div hidden={step !== 1}><button key="submit" type="submit" data-intent="submit" className="button button-light summary-button" disabled={step !== 1 || sending || !catalogReady || !tariff || !store.acceptingRequests}>{sending ? t('Надсилаємо…', 'Отправляем…') : t('Забронювати', 'Забронировать')}{!sending && <ArrowUpRight size={20} />}</button></div>
        <div className="booking-reassurance"><ShieldCheck size={20} weight="light" aria-hidden="true" /><p><strong>{t('Без оплати на сайті', 'Без оплаты на сайте')}</strong><span>{t('Зв’яжемося з тобою, щоб підтвердити наявність, доставку та умови оренди.', 'Свяжемся с тобой, чтобы подтвердить наличие, доставку и условия аренды.')}</span></p></div>
      </div>
    </form>}
  </div>{pickerOpen && <GamePicker requestedGame={requestedGame} onRequestedGameChange={setRequestedGame} games={store.games} consoleId={consoleId} selected={gameIds} maxGames={maxGames} onToggle={onToggleGame} onClose={closePicker} />}</section>;
}
