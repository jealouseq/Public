import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ArrowUpRight, ArrowRight, Check, CheckCircle, GameController, ShieldCheck, X, CaretLeft } from '@phosphor-icons/react';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { addRentalDays, dayLabel, money, quote, todayInKyiv, validateStartDate, type ConsoleId } from '../lib/rental';
import { submitRequest, type StoreCatalog, type RequestReceipt } from '../lib/api';

import { useI18n } from '../lib/i18n';

function requestId() { return globalThis.crypto.randomUUID?.() ?? Array.from(globalThis.crypto.getRandomValues(new Uint8Array(16))).map(value => value.toString(16).padStart(2, '0')).join(''); }

export function Booking({ store, consoleId, days, gameIds, onConsole, onDays, onToggleGame, onLegal }: {
  store: StoreCatalog; consoleId: ConsoleId; days: number; gameIds: string[];
  onConsole: (id: ConsoleId) => void; onDays: (days: number) => void; onToggleGame: (id: string) => void;
  onLegal: (page: 'privacy' | 'terms') => void;
}) {
  const { t, language } = useI18n();
  const today = todayInKyiv();
  const lastDate = new Date(`${today}T12:00:00Z`);
  lastDate.setUTCFullYear(lastDate.getUTCFullYear() + 1);
  const maxStart = lastDate.toISOString().slice(0, 10);
  const [start, setStart] = useState(today);
  const [controllers, setControllers] = useState(store.settings.baseControllers);
  const [method, setMethod] = useState<'delivery' | 'pickup'>('delivery');
  const [step, setStep] = useState(0);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [address, setAddress] = useState('');
  const [consent, setConsent] = useState(false);
  const [website, setWebsite] = useState('');
  const [error, setError] = useState('');
  useEffect(() => { setError(''); }, [language]);
  const [sending, setSending] = useState(false);
  const [receipt, setReceipt] = useState<(RequestReceipt & { consoleId: ConsoleId; days: number; start: string; end: string }) | null>(null);
  const firstInput = useRef<HTMLInputElement>(null);
  const formHeading = useRef<HTMLDivElement>(null);
  const attempt = useRef({ fingerprint: '', id: requestId() });
  const tariff = quote(consoleId, days, store.tariffs);
  const validDate = validateStartDate(start, today);
  const end = validDate ? addRentalDays(start, days) : '';
  const deposit = consoleId === 'ps5' ? store.settings.depositPs5 : store.settings.depositPs4;
  const extraCount = Math.max(0, controllers - store.settings.baseControllers);
  const extraPrice = extraCount ? store.settings.extraControllerFee : 0;
  const deliveryPrice = method === 'pickup' ? 0 : store.settings.deliveryFee === null ? null : days >= store.settings.freeDeliveryFrom ? 0 : store.settings.deliveryFee;
  const knownSubtotal = tariff.price + (extraPrice ?? 0) + (deliveryPrice ?? 0);
  useEffect(() => { setControllers(store.settings.baseControllers); }, [store.settings.baseControllers]);
  useEffect(() => { if (!store.settings.pickup && method === 'pickup') setMethod('delivery'); }, [method, store.settings.pickup]);
  useEffect(() => { if (step === 1) { formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center' }); firstInput.current?.focus({ preventScroll: true }); } }, [step]);
  async function submit(event: FormEvent) {
    event.preventDefault();
    if (sending) return;
    if (!validDate) { setError(t("Оберіть коректну дату отримання.", "Выбери корректную дату получения.")); return; }
    if (!store.acceptingRequests) { setError(t("Зараз магазин не приймає заявки. Спробуй ще раз трохи пізніше.", "Сейчас магазин не принимает заявки. Попробуй ещё раз чуть позже.")); return; }
    const base = { console: consoleId, days, startDate: start, controllers, gameIds, name, phone, method, address, consent, website };
    const fingerprint = JSON.stringify(base);
    if (attempt.current.fingerprint && attempt.current.fingerprint !== fingerprint) attempt.current.id = requestId();
    attempt.current.fingerprint = fingerprint;
    setSending(true); setError('');
    try {
      const result = await submitRequest({ ...base, language, requestId: attempt.current.id });
      setReceipt({ ...result, consoleId, days, start, end });
      requestAnimationFrame(() => formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center' }));
    } catch (cause) { setError(cause instanceof Error ? cause.message : t("Не вдалося надіслати заявку.", "Не удалось отправить заявку.")); }
    finally { setSending(false); }
  }
  return <section className="section booking-section" id="booking"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">{t("ЗАЯВКА НА ОРЕНДУ", "ЗАЯВКА НА АРЕНДУ")}</p><h2>{t("Розрахуй оренду.", "Рассчитай аренду.")}</h2></div><p className="heading-side-copy">{t("Обери дату отримання. Ціну за термін покажемо одразу.", "Выбери дату получения. Стоимость за весь срок покажем сразу.")}</p></Reveal>
    {receipt ? <div className="booking-success" ref={formHeading} role="status"><CheckCircle size={52} weight="light" /><p className="eyebrow">{t("ЗАЯВКА ", "ЗАЯВКА ")}{receipt.reference}</p><h3>{t("Заявку отримано.", "Заявка получена.")}</h3><p>{t("Ми зв’яжемося з тобою, перевіримо доступність консолі та узгодимо доставку, комплектацію і заставу.", "Мы свяжемся с тобой, проверим доступность консоли и согласуем доставку, комплектацию и залог.")}</p><div className="success-recap"><span>{receipt.consoleId.toUpperCase()} · {dayLabel(receipt.days, language)}</span><strong>{money(receipt.rentalAmount)} {t(" грн", " грн")}</strong><span>{receipt.start.split('-').reverse().join('.')} — {receipt.end.split('-').reverse().join('.')}</span></div><p className="muted small">{t("Заявка очікує підтвердження. Оплата ще не потрібна.", "Заявка ожидает подтверждения. Оплата пока не нужна.")}</p><button className="button button-outline" onClick={() => { setReceipt(null); setStep(0); setConsent(false); attempt.current = { fingerprint: '', id: requestId() }; }}>{t("Нова заявка ", "Новая заявка ")}<ArrowUpRight size={18} /></button></div> :
    <form className="booking-layout" onSubmit={submit}>
      <div className="booking-fields">
        <div className="booking-row row-top"><span className="field-label">{t("Консоль", "Консоль")}</span><ConsoleSwitch value={consoleId} onChange={onConsole} id="booking" /></div>
        <fieldset className="term-field"><legend className="field-label">{t("Термін оренди", "Срок аренды")}</legend><div className="term-options">{store.tariffs[consoleId].map(item => <button type="button" key={item.days} aria-pressed={days === item.days} className={days === item.days ? 'selected' : ''} onClick={() => onDays(item.days)}>{dayLabel(item.days, language)}</button>)}</div></fieldset>
        <div className="date-fields"><label><span className="field-label">{t("Отримання", "Получение")}</span><input type="date" aria-label={t("Дата отримання", "Дата получения")} min={today} max={maxStart} value={start} onChange={event => setStart(event.target.value)} required aria-invalid={!validDate} /></label><label><span className="field-label">{t("Повернення", "Возврат")}</span><input type="date" aria-label={t("Дата повернення", "Дата возврата")} value={end} readOnly /><span className="input-help">{t("Розраховано за обраним терміном", "Рассчитано по выбранному сроку")}</span></label></div>
        {!validDate && <p className="field-error">{t("Оберіть сьогоднішню або майбутню дату.", "Выбери сегодняшнюю или будущую дату.")}</p>}
        <div className="controller-row"><div><span className="field-label">{t("Геймпади", "Геймпады")}</span><span className="input-help">{t("Для гри разом", "Для совместной игры")}</span></div><div className="controller-options">{[1, 2].map(value => <button type="button" key={value} className={controllers === value ? 'selected' : ''} aria-pressed={controllers === value} onClick={() => setControllers(value)}><GameController size={21} weight="light" />{value}</button>)}</div></div>
        <div className="booking-games"><div className="booking-row"><span className="field-label">{t("Ігри за бажанням", "Игры по желанию")}</span><a href="#games" className="text-link">{t("Обрати ігри ", "Выбрать игры ")}<ArrowUpRight size={15} /></a></div>{gameIds.length ? <div className="selected-games">{gameIds.map(id => <button type="button" className="selected-game" key={id} onClick={() => onToggleGame(id)}>{store.games.find(game => game.id === id)?.title}<X size={14} /></button>)}</div> : <p className="muted small">{t("Обери з добірки вище або підкажемо після заявки.", "Выбери из подборки выше или подскажем после заявки.")}</p>}</div>
        {step === 1 && <div className="customer-fields" ref={formHeading}><div className="customer-heading"><h3>{t("Як з тобою зв’язатися?", "Как с тобой связаться?")}</h3><button type="button" className="text-link" onClick={() => setStep(0)}><CaretLeft size={15} /> {t(" Назад", " Назад")}</button></div><div className="customer-inputs"><label><span className="field-label">{t("Ім’я", "Имя")}</span><input ref={firstInput} name="name" autoComplete="given-name" placeholder={t("Твоє ім’я", "Твоё имя")} value={name} onChange={event => setName(event.target.value)} minLength={2} maxLength={100} required /></label><label><span className="field-label">{t("Телефон", "Телефон")}</span><input type="tel" name="phone" autoComplete="tel" placeholder="+380 __ ___ __ __" value={phone} onChange={event => setPhone(event.target.value)} minLength={10} maxLength={24} required /></label></div>{store.settings.pickup && <fieldset className="delivery-method"><legend className="field-label">{t("Отримання", "Получение")}</legend><label><input type="radio" name="method" checked={method === 'delivery'} onChange={() => setMethod('delivery')} /> {t(" Доставка", " Доставка")}</label><label><input type="radio" name="method" checked={method === 'pickup'} onChange={() => setMethod('pickup')} /> {t(" Самовивіз", " Самовывоз")}</label></fieldset>}{method === 'delivery' && <label className="address-field"><span className="field-label">{t("Місто та адреса", "Город и адрес")}</span><input name="address" autoComplete="street-address" placeholder={t("Місто, вулиця, будинок", "Город, улица, дом")} value={address} onChange={event => setAddress(event.target.value)} minLength={5} maxLength={300} required /><span className="input-help">{t("Перевіримо зону доставки та узгодимо зручний час.", "Проверим зону доставки и согласуем удобное время.")}</span></label>}<label className="consent"><input type="checkbox" checked={consent} onChange={event => setConsent(event.target.checked)} required /><span>{t("Погоджуюся з ", "Соглашаюсь с ")}<button type="button" onClick={() => onLegal('terms')}>{t("умовами оренди", "условиями аренды")}</button> {t(" та ", " и ")}<button type="button" onClick={() => onLegal('privacy')}>{t("обробкою персональних даних", "обработкой персональных данных")}</button>.</span></label><label className="honeypot" aria-hidden="true">{t("Ваш сайт", "Ваш сайт")}<input name="website" value={website} onChange={event => setWebsite(event.target.value)} autoComplete="off" tabIndex={-1} /></label></div>}
      </div>
      <aside className="booking-summary"><p className="eyebrow">{t("ТВОЯ ОРЕНДА", "ТВОЯ АРЕНДА")}</p><h3>{consoleId === 'ps5' ? 'PlayStation 5' : 'PlayStation 4'}</h3><div className="summary-price" aria-live="polite"><strong>{money(tariff.price)}</strong><span>{t("грн / ", "грн / ")}{dayLabel(days, language)}</span></div><dl className="summary-lines">{extraCount > 0 && <div><dt>{t("Додатковий геймпад", "Дополнительный геймпад")}</dt><dd>{extraPrice === null ? t("Узгодимо", "Согласуем") : `${money(extraPrice)} грн`}</dd></div>}<div><dt>{method === 'pickup' ? t("Самовивіз", "Самовывоз") : t("Доставка", "Доставка")}</dt><dd>{deliveryPrice === null ? t("За адресою", "По адресу") : `${money(deliveryPrice)} грн`}</dd></div><div><dt>{t("Застава ", "Залог ")}<span className="muted">{t("(повертається)", "(возвращается)")}</span></dt><dd>{deposit === null ? t("Узгодимо", "Согласуем") : `${money(deposit)} грн`}</dd></div></dl><div className="summary-subtotal"><span>{t("Попередня сума", "Предварительная сумма")}</span><strong>{money(knownSubtotal)} {t(" грн", " грн")}</strong></div><p className="summary-note">{t("Доставка, наявність ігор і застава — після підтвердження. Без оплати на цьому кроці.", "Доставку, наличие игр и залог подтвердим лично. На этом шаге оплата не нужна.")}</p>{error && <p role="alert" className="form-error">{error}</p>}{step === 0 ? <button type="button" className="button button-light summary-button" disabled={!validDate} onClick={() => setStep(1)}>{t("Продовжити ", "Продолжить ")}<ArrowRight size={20} /></button> : <button type="submit" className="button button-light summary-button" disabled={sending}>{sending ? t("Надсилаємо…", "Отправляем…") : t("Надіслати заявку", "Отправить заявку")}{!sending && <ArrowUpRight size={20} />}</button>}<p className="summary-trust"><ShieldCheck size={17} weight="light" /> {t(" Спочатку все узгодимо", " Сначала всё согласуем")}</p>
      </aside>
    </form>}
  </div></section>;
}
