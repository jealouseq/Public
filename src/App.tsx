import { useEffect, useRef, useState } from 'react';
import { X } from '@phosphor-icons/react';
import { Header } from './sections/Header';
import { Hero } from './sections/Hero';
import { Tariffs } from './sections/Tariffs';
import { Booking } from './sections/Booking';
import { Games } from './sections/Games';
import { Experience, InTheBox, HowItWorks, Delivery } from './sections/Story';
import { FAQ } from './sections/FAQ';
import { Footer } from './sections/Footer';
import { boot, fallbackCatalog, getCatalog, imageUrl } from './lib/api';
import { safeDays, type ConsoleId } from './lib/rental';

export default function App() {
  const [store, setStore] = useState(fallbackCatalog);
  const [consoleId, setConsoleId] = useState<ConsoleId>('ps5');
  const [days, setDays] = useState(3);
  const [gameIds, setGameIds] = useState<string[]>([]);
  const [legal, setLegal] = useState<'privacy' | 'terms' | null>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const legalOpener = useRef<HTMLElement | null>(null);
  useEffect(() => { let active = true; getCatalog().then(data => { if (active) setStore(data); }).catch(() => {}); return () => { active = false; }; }, []);
  useEffect(() => { const favicon = document.createElement('link'); favicon.rel = 'icon'; favicon.type = 'image/webp'; favicon.href = imageUrl('favicon'); document.head.append(favicon); return () => { favicon.remove(); }; }, []);
  useEffect(() => { if (legal) dialog.current?.showModal(); }, [legal]);
  function selectConsole(value: ConsoleId) { setConsoleId(value); setDays(current => safeDays(value, current)); setGameIds(current => current.filter(id => store.games.find(game => game.id === id)?.platforms.includes(value))); }
  function choose(days: number) { setDays(days); document.getElementById('booking')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); }
  function toggleGame(id: string) { setGameIds(current => current.includes(id) ? current.filter(game => game !== id) : [...current, id]); }
  function showLegal(page: 'privacy' | 'terms') { legalOpener.current = document.activeElement as HTMLElement; setLegal(page); }
  function closeLegal() { dialog.current?.close(); setLegal(null); legalOpener.current?.focus(); }
  return <><a className="skip-link" href="#main-content">Перейти до вмісту</a><Header /><main id="main-content"><Hero onChoosePS5={() => selectConsole('ps5')} price={store.tariffs.ps5.find(item => item.days === 1)!.price} /><Tariffs freeDeliveryFrom={store.settings.freeDeliveryFrom} consoleId={consoleId} tariffs={store.tariffs[consoleId]} onConsole={selectConsole} onChoose={choose} /><Games games={store.games} consoleId={consoleId} selected={gameIds} onToggle={toggleGame} /><Booking store={store} consoleId={consoleId} days={days} gameIds={gameIds} onConsole={selectConsole} onDays={setDays} onToggleGame={toggleGame} onLegal={showLegal} /><Experience /><InTheBox /><HowItWorks /><Delivery settings={store.settings} /><FAQ /></main><Footer consoleId={consoleId} minimumDays={store.tariffs[consoleId][0].days} price={store.tariffs[consoleId][0].price} settings={store.settings} onLegal={showLegal} />
    {legal && <dialog ref={dialog} className="legal-dialog" aria-labelledby="legal-dialog-title" onCancel={event => { event.preventDefault(); closeLegal(); }} onClick={event => { if (event.target === event.currentTarget) closeLegal(); }}><div><button className="icon-button dialog-close" aria-label="Закрити" onClick={closeLegal}><X size={22} /></button><p className="eyebrow">JOYRENT</p><h2 id="legal-dialog-title">{legal === 'terms' ? 'Умови оренди' : 'Твої дані'}</h2>{legal === 'terms' ? <><p>Надсилання заявки не є підтвердженням бронювання та не потребує оплати. Після заявки ми перевіримо доступність консолі й ігор та зв’яжемося з тобою.</p><p>До підтвердження узгодимо комплектацію, отримання й повернення, вартість доставки, заставу та відповідальність за обладнання. Ціна оренди залежить від консолі та обраного терміну.</p><p>Продовження можливе після перевірки доступності. Не розбирай обладнання; повідом нас, якщо виникла несправність або пошкодження.</p></> : <><p>Ім’я, телефон, місто й адреса потрібні, щоб обробити заявку, узгодити оренду та доставку. Ми не просимо пароль PSN, платіжні дані або документи в цій формі.</p><p>Заявка зберігається у магазині JOYRENT. Доступ до неї мають уповноважені працівники магазину. Для уточнення або видалення даних звернися через контактний канал магазину.</p><p>Ця форма не підписує тебе на рекламну розсилку.</p></>}{(legal === 'privacy' ? boot.privacyUrl : boot.termsUrl) && <a className="text-link" href={legal === 'privacy' ? boot.privacyUrl : boot.termsUrl}>Повна інформація</a>}<button className="button button-outline" onClick={closeLegal}>Зрозуміло</button></div></dialog>}
  </>;
}
