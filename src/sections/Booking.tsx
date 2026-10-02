import { useEffect, useRef, useState, type FormEvent } from 'react';
import { ArrowUpRight, ArrowRight, Check, CheckCircle, GameController, ShieldCheck, X, CaretLeft } from '@phosphor-icons/react';
import { ConsoleSwitch } from '../../components/ui/console-switch';
import { Reveal } from '../../components/ui/reveal';
import { addRentalDays, dayLabel, money, quote, todayInKyiv, validateStartDate, type ConsoleId } from '../lib/rental';
import { submitRequest, type StoreCatalog, type RequestReceipt } from '../lib/api';

function requestId() { return globalThis.crypto.randomUUID?.() ?? Array.from(globalThis.crypto.getRandomValues(new Uint8Array(16))).map(value => value.toString(16).padStart(2, '0')).join(''); }
export function Booking({ store, consoleId, days, gameIds, onConsole, onDays, onToggleGame, onLegal }: {
  store: StoreCatalog; consoleId: ConsoleId; days: number; gameIds: string[];
  onConsole: (id: ConsoleId) => void; onDays: (days: number) => void; onToggleGame: (id: string) => void;
  onLegal: (page: 'privacy' | 'terms') => void;
}) {
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
    if (!validDate) { setError('Оберіть коректну дату отримання.'); return; }
    if (!store.acceptingRequests) { setError('Зараз магазин не приймає заявки. Спробуй ще раз трохи пізніше.'); return; }
    const base = { console: consoleId, days, startDate: start, controllers, gameIds, name, phone, method, address, consent, website };
    const fingerprint = JSON.stringify(base);
    if (attempt.current.fingerprint && attempt.current.fingerprint !== fingerprint) attempt.current.id = requestId();
    attempt.current.fingerprint = fingerprint;
    setSending(true); setError('');
    try {
      const result = await submitRequest({ ...base, requestId: attempt.current.id });
      setReceipt({ ...result, consoleId, days, start, end });
      requestAnimationFrame(() => formHeading.current?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center' }));
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Не вдалося надіслати заявку.'); }
    finally { setSending(false); }
  }
  return <section className="section booking-section" id="booking"><div className="shell">
    <Reveal className="section-heading"><div><p className="eyebrow">УСЕ ПОЧИНАЄТЬСЯ З ДАТИ</p><h2>Твоя гра.<br />Твій план.</h2></div><p className="heading-side-copy">Обери консоль і дати.<br />Вартість оренди — одразу перед тобою.</p></Reveal>
    {receipt ? <div className="booking-success" ref={formHeading} role="status"><CheckCircle size={52} weight="light" /><p className="eyebrow">ЗАЯВКА {receipt.reference}</p><h3>Твій план уже в нас.</h3><p>Ми зв’яжемося з тобою, перевіримо доступність консолі та узгодимо доставку, комплектацію і заставу.</p><div className="success-recap"><span>{receipt.consoleId.toUpperCase()} · {dayLabel(receipt.days)}</span><strong>{money(receipt.rentalAmount)} грн</strong><span>{receipt.start.split('-').reverse().join('.')} — {receipt.end.split('-').reverse().join('.')}</span></div><p className="muted small">Заявка очікує підтвердження. Оплата ще не потрібна.</p><button className="button button-outline" onClick={() => { setReceipt(null); setStep(0); setConsent(false); attempt.current = { fingerprint: '', id: requestId() }; }}>Запланувати ще одну гру <ArrowUpRight size={18} /></button></div> :
    <form className="booking-layout" onSubmit={submit}>
      <div className="booking-fields">
        <div className="booking-row row-top"><span className="field-label">01 / Консоль</span><ConsoleSwitch value={consoleId} onChange={onConsole} id="booking" /></div>
        <fieldset className="term-field"><legend className="field-label">02 / Термін оренди</legend><div className="term-options">{store.tariffs[consoleId].map(item => <button type="button" key={item.days} aria-pressed={days === item.days} className={days === item.days ? 'selected' : ''} onClick={() => onDays(item.days)}>{dayLabel(item.days)}</button>)}</div></fieldset>
        <div className="date-fields"><label><span className="field-label">03 / Отримання</span><input type="date" aria-label="Дата отримання" min={today} max={maxStart} value={start} onChange={event => setStart(event.target.value)} required aria-invalid={!validDate} /></label><label><span className="field-label">Повернення</span><input type="date" aria-label="Дата повернення" value={end} readOnly /><span className="input-help">Розраховано за обраним терміном</span></label></div>
        {!validDate && <p className="field-error">Оберіть сьогоднішню або майбутню дату.</p>}
        <div className="controller-row"><div><span className="field-label">04 / Геймпади</span><span className="input-help">Для гри разом</span></div><div className="controller-options">{[1, 2].map(value => <button type="button" key={value} className={controllers === value ? 'selected' : ''} aria-pressed={controllers === value} onClick={() => setControllers(value)}><GameController size={21} weight="light" />{value}</button>)}</div></div>
        <div className="booking-games"><div className="booking-row"><span className="field-label">05 / Ігри за бажанням</span><a href="#games" className="text-link">Обрати ігри <ArrowUpRight size={15} /></a></div>{gameIds.length ? <div className="selected-games">{gameIds.map(id => <button type="button" className="selected-game" key={id} onClick={() => onToggleGame(id)}>{store.games.find(game => game.id === id)?.title}<X size={14} /></button>)}</div> : <p className="muted small">Обери з добірки вище або підкажемо після заявки.</p>}</div>
        {step === 1 && <div className="customer-fields" ref={formHeading}><div className="customer-heading"><h3>Як з тобою зв’язатися?</h3><button type="button" className="text-link" onClick={() => setStep(0)}><CaretLeft size={15} /> Назад</button></div><div className="customer-inputs"><label><span className="field-label">Ім’я</span><input ref={firstInput} name="name" autoComplete="given-name" placeholder="Твоє ім’я" value={name} onChange={event => setName(event.target.value)} minLength={2} maxLength={100} required /></label><label><span className="field-label">Телефон</span><input type="tel" name="phone" autoComplete="tel" placeholder="+380 __ ___ __ __" value={phone} onChange={event => setPhone(event.target.value)} minLength={10} maxLength={24} required /></label></div>{store.settings.pickup && <fieldset className="delivery-method"><legend className="field-label">Отримання</legend><label><input type="radio" name="method" checked={method === 'delivery'} onChange={() => setMethod('delivery')} /> Доставка</label><label><input type="radio" name="method" checked={method === 'pickup'} onChange={() => setMethod('pickup')} /> Самовивіз</label></fieldset>}{method === 'delivery' && <label className="address-field"><span className="field-label">Місто та адреса</span><input name="address" autoComplete="street-address" placeholder="Місто, вулиця, будинок" value={address} onChange={event => setAddress(event.target.value)} minLength={5} maxLength={300} required /><span className="input-help">Перевіримо зону доставки та узгодимо зручний час.</span></label>}<label className="consent"><input type="checkbox" checked={consent} onChange={event => setConsent(event.target.checked)} required /><span>Погоджуюся з <button type="button" onClick={() => onLegal('terms')}>умовами оренди</button> та <button type="button" onClick={() => onLegal('privacy')}>обробкою персональних даних</button>.</span></label><label className="honeypot" aria-hidden="true">Ваш сайт<input name="website" value={website} onChange={event => setWebsite(event.target.value)} autoComplete="off" tabIndex={-1} /></label></div>}
      </div>
      <aside className="booking-summary"><p className="eyebrow">ТВІЙ JOYRENT</p><h3>{consoleId === 'ps5' ? 'PlayStation 5' : 'PlayStation 4'}</h3><p className="summary-term">{tariff.name} · {dayLabel(days)}</p><div className="summary-price" aria-live="polite"><strong>{money(tariff.price)}</strong><span>грн / {dayLabel(days)}</span></div><dl className="summary-lines"><div><dt>Оренда</dt><dd>{money(tariff.price)} грн</dd></div>{extraCount > 0 && <div><dt>Додатковий геймпад</dt><dd>{extraPrice === null ? 'Узгодимо' : `${money(extraPrice)} грн`}</dd></div>}<div><dt>{method === 'pickup' ? 'Самовивіз' : 'Доставка'}</dt><dd>{deliveryPrice === null ? 'За адресою' : `${money(deliveryPrice)} грн`}</dd></div><div><dt>Застава <span className="muted">(повертається)</span></dt><dd>{deposit === null ? 'Узгодимо' : `${money(deposit)} грн`}</dd></div></dl><div className="summary-subtotal"><span>Попередня сума</span><strong>{money(knownSubtotal)} грн</strong></div><p className="summary-note">Доставка, наявність ігор і застава — після підтвердження. Без оплати на цьому кроці.</p>{error && <p role="alert" className="form-error">{error}</p>}{step === 0 ? <button type="button" className="button button-light summary-button" disabled={!validDate} onClick={() => setStep(1)}>Продовжити <ArrowRight size={20} /></button> : <button type="submit" className="button button-light summary-button" disabled={sending}>{sending ? 'Надсилаємо…' : 'Надіслати заявку'}{!sending && <ArrowUpRight size={20} />}</button>}<p className="summary-trust"><ShieldCheck size={17} weight="light" /> Спочатку все узгодимо</p>
      </aside>
    </form>}
  </div></section>;
}
