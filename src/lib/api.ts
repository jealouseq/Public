import { defaultCatalog, uniqueGames, type ConsoleId, type Game, type Tariff } from './rental';

export interface StoreSettings {
  city: string; cityRu?: string; phone: string; email: string; telegram: string;
  deliveryFee: number | null; deliveryGreenFee?: number | null; deliveryYellowFee?: number | null; depositPs5: number | null; depositPs4: number | null;
  baseControllers: number; extraControllerFee: number | null; pickup: boolean;
  freeDeliveryFrom: number; deliveryText: string; deliveryTextRu?: string; maxGames?: number;
}
export interface BootConfig {
  apiBase: string; assetBase: string; nonce?: string; privacyUrl?: string; termsUrl?: string; preview?: boolean; privacyRuUrl?: string; termsRuUrl?: string; faqUrl?: string; faqRuUrl?: string;
}
declare global { interface Window { JOYRENT?: BootConfig } }
export const boot: BootConfig = window.JOYRENT ?? { apiBase: '/wp-api/joyrent/v1', assetBase: '' };
export const imageUrl = (name: string) => `${boot.assetBase}/images/${name}.webp`;
export const fallbackSettings: StoreSettings = {
  city: 'Одеса', cityRu: 'Одесса', phone: '+380996669946', email: '', telegram: 'https://t.me/joyrent_od', deliveryFee: null, deliveryGreenFee: 200, deliveryYellowFee: 300, depositPs5: 25000, depositPs4: 7500,
  baseControllers: 2, extraControllerFee: 0, pickup: false, freeDeliveryFrom: 7, maxGames: 100,
  deliveryText: 'Доставляємо Одесою. Привеземо, підключимо та заберемо після оренди. Зону й час підтвердимо за адресою.',
  deliveryTextRu: 'Доставляем по Одессе. Привезём, подключим и заберём после аренды. Зону и время подтвердим по адресу.',
};
export type StoreCatalog = { tariffs: Record<ConsoleId, Tariff[]>; games: Game[]; settings: StoreSettings; currency: string; acceptingRequests: boolean };
export type CatalogStatus = 'loading' | 'error' | 'ready';
export const fallbackCatalog: StoreCatalog = { ...defaultCatalog, settings: fallbackSettings, currency: 'UAH', acceptingRequests: false };
export interface RentalPayload {
  language?: 'uk' | 'ru'; console: ConsoleId; days: number; startDate: string; controllers: number; gameIds: string[];
  name: string; phone: string; method: 'delivery' | 'pickup'; address: string;
  consent: boolean; website: string; requestId: string;
}
export type RequestReceipt = { reference: string; rentalAmount: number; status: 'awaiting_confirmation' };
const requestFailure = () => new URLSearchParams(window.location.search).get('lang') === 'ru' ? 'Не удалось отправить заявку. Проверь подключение и попробуй ещё раз.' : 'Не вдалося надіслати заявку. Перевір з’єднання та спробуй ще раз.';
async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const response = await fetch(`${boot.apiBase}${path}`, {
    ...options, headers: { 'Content-Type': 'application/json', ...(boot.nonce ? { 'X-WP-Nonce': boot.nonce } : {}), ...options?.headers },
  }).catch(() => { throw new Error(requestFailure()); });
  const data = await response.json().catch(() => null);
  if (!response.ok) {
    const submittedLanguage = options?.body && typeof options.body === 'string' ? JSON.parse(options.body).language ?? 'uk' : 'uk';
    const currentLanguage = new URLSearchParams(window.location.search).get('lang') === 'ru' ? 'ru' : 'uk';
    throw new Error(submittedLanguage === currentLanguage && data?.code?.startsWith('jr_') ? data.message : requestFailure());
  }
  return data as T;
}
export function normalizeCatalog(data: StoreCatalog): StoreCatalog {
  if (!data || !Array.isArray(data.games) || !data.tariffs || !Array.isArray(data.tariffs.ps5) || !Array.isArray(data.tariffs.ps4)) throw new Error(requestFailure());
  const tariffs = (items: Tariff[]) => items.filter((item, index) => Number.isInteger(item.days) && item.days > 0 && item.days <= 30 && Number.isFinite(item.price) && item.price >= 0 && items.findIndex(other => other.days === item.days) === index).sort((a, b) => a.days - b.days);
  return { ...data, games: uniqueGames(data.games), tariffs: { ps5: tariffs(data.tariffs.ps5), ps4: tariffs(data.tariffs.ps4) }, settings: { ...fallbackSettings, ...data.settings } };
}
export const getCatalog = async () => normalizeCatalog(boot.preview ? fallbackCatalog : await request<StoreCatalog>('/catalog'));
export const submitRequest = (payload: RentalPayload) => request<RequestReceipt>('/requests', { method: 'POST', body: JSON.stringify(payload) });

export const faqUrl = (language: 'uk' | 'ru') => boot.preview ? language === 'ru' ? './faq-ru.html' : './faq.html' : (language === 'ru' ? boot.faqRuUrl : boot.faqUrl) || (language === 'ru' ? '/faq-ru/' : '/faq/');
