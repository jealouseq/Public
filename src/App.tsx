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
import { safeDays, type ConsoleId } from './lib/rental';
import { useI18n } from './lib/i18n';

export default function App() {
  const [store, setStore] = useState(fallbackCatalog);
  const [consoleId, setConsoleId] = useState<ConsoleId>('ps5');
  const [days, setDays] = useState(3);
  const [gameIds, setGameIds] = useState<string[]>([]);
  const [legal, setLegal] = useState<'privacy' | 'terms' | null>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const legalOpener = useRef<HTMLElement | null>(null);
  const { t, language } = useI18n();
  useEffect(() => { let active = true; getCatalog().then(data => { if (active) setStore(data); }).catch(() => {}); return () => { active = false; }; }, []);
  useEffect(() => { const favicon = document.createElement('link'); favicon.rel = 'icon'; favicon.type = 'image/webp'; favicon.href = imageUrl('favicon'); document.head.append(favicon); return () => { favicon.remove(); }; }, []);
  useEffect(() => { if (legal) dialog.current?.showModal(); }, [legal]);
  function selectConsole(value: ConsoleId) { setConsoleId(value); setDays(current => safeDays(value, current)); setGameIds(current => current.filter(id => store.games.find(game => game.id === id)?.platforms.includes(value))); }
  function choose(days: number) { setDays(days); document.getElementById('booking')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); }
  function toggleGame(id: string) { setGameIds(current => current.includes(id) ? current.filter(game => game !== id) : [...current, id]); }
  function showLegal(page: 'privacy' | 'terms') { legalOpener.current = document.activeElement as HTMLElement; setLegal(page); }
  function closeLegal() { dialog.current?.close(); setLegal(null); legalOpener.current?.focus(); }
  const legalUrl = legal === 'privacy' ? language === 'ru' ? boot.privacyRuUrl : boot.privacyUrl : language === 'ru' ? boot.termsRuUrl : boot.termsUrl;
  return <><a className="skip-link" href="#main-content">{t('Перейти до вмісту', 'Перейти к содержимому')}</a><Header /><main id="main-content"><Hero onChoosePS5={() => selectConsole('ps5')} price={store.tariffs.ps5.find(item => item.days === 1)!.price} /><Tariffs freeDeliveryFrom={store.settings.freeDeliveryFrom} consoleId={consoleId} tariffs={store.tariffs[consoleId]} onConsole={selectConsole} onChoose={choose} /><Games games={store.games} consoleId={consoleId} selected={gameIds} onToggle={toggleGame} /><Kit /><HowItWorks settings={store.settings} /><Booking store={store} consoleId={consoleId} days={days} gameIds={gameIds} onConsole={selectConsole} onDays={setDays} onToggleGame={toggleGame} onLegal={showLegal} /></main><Footer consoleId={consoleId} minimumDays={store.tariffs[consoleId][0].days} price={store.tariffs[consoleId][0].price} settings={store.settings} onLegal={showLegal} />
    {legal && <dialog ref={dialog} className="legal-dialog" aria-labelledby="legal-dialog-title" onCancel={event => { event.preventDefault(); closeLegal(); }} onClick={event => { if (event.target === event.currentTarget) closeLegal(); }}><div><button className="icon-button dialog-close" aria-label={t('Закрити', 'Закрыть')} onClick={closeLegal}><X size={22} /></button><p className="eyebrow">JOYRENT</p><h2 id="legal-dialog-title">{legal === 'terms' ? t('Умови оренди', 'Условия аренды') : t('Конфіденційність', 'Конфиденциальность')}</h2>{legal === 'terms' ? <><p>{t('Надсилання заявки не підтверджує бронювання й не потребує оплати. Ми перевіримо доступність консолі та ігор і зв’яжемося з тобою.', 'Отправка заявки не подтверждает бронирование и не требует оплаты. Мы проверим доступность консоли и игр и свяжемся с тобой.')}</p><p>{t('До підтвердження узгодимо комплектацію, дати й час отримання та повернення, доставку, заставу та відповідальність за обладнання.', 'До подтверждения согласуем комплектацию, даты и время получения и возврата, доставку, залог и ответственность за оборудование.')}</p><p>{t('Продовження можливе після перевірки доступності. Не розбирай обладнання та одразу повідомляй про несправності.', 'Продление возможно после проверки доступности. Не разбирай оборудование и сразу сообщай о неисправностях.')}</p></> : <><p>{t('Ім’я, телефон, місто й адреса потрібні для обробки заявки та узгодження доставки. Не надсилай пароль PSN, документи чи платіжні дані.', 'Имя, телефон, город и адрес нужны для обработки заявки и согласования доставки. Не отправляй пароль PSN, документы или платёжные данные.')}</p><p>{t('Заявка зберігається у магазині JOYRENT. Доступ мають уповноважені працівники. Для уточнення або видалення даних звернися через контактний канал магазину.', 'Заявка хранится в магазине JOYRENT. Доступ имеют уполномоченные сотрудники. Для уточнения или удаления данных обратись через контактный канал магазина.')}</p><p>{t('Форма не підписує тебе на рекламну розсилку.', 'Форма не подписывает тебя на рекламную рассылку.')}</p></>}{legalUrl && <a className="text-link" href={legalUrl}>{t('Повна інформація', 'Полная информация')}</a>}<button className="button button-outline" onClick={closeLegal}>{t('Зрозуміло', 'Понятно')}</button></div></dialog>}
  </>;
}
