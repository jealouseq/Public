import { defaultCatalog, type ConsoleId, type Game, type Tariff } from './rental';

export interface StoreSettings {
  city: string; phone: string; email: string; telegram: string;
  deliveryFee: number | null; depositPs5: number | null; depositPs4: number | null;
  baseControllers: number; extraControllerFee: number | null; pickup: boolean;
  freeDeliveryFrom: number; deliveryText: string; deliveryTextRu?: string;
}
export interface BootConfig {
  apiBase: string; assetBase: string; nonce?: string; privacyUrl?: string; termsUrl?: string; preview?: boolean; privacyRuUrl?: string; termsRuUrl?: string;
}
declare global { interface Window { JOYRENT?: BootConfig } }
export const boot: BootConfig = window.JOYRENT ?? { apiBase: '/wp-api/joyrent/v1', assetBase: '' };
export const imageUrl = (name: string) => `${boot.assetBase}/images/${name}.webp`;
export const fallbackSettings: StoreSettings = {
  city: '', phone: '', email: '', telegram: '', deliveryFee: null, depositPs5: null, depositPs4: null,
  baseControllers: 1, extraControllerFee: null, pickup: false, freeDeliveryFrom: 7,
  deliveryText: 'Вкажи місто та адресу у заявці. Ми перевіримо можливість доставки й узгодимо час отримання та повернення.',
};
export type StoreCatalog = { tariffs: Record<ConsoleId, Tariff[]>; games: Game[]; settings: StoreSettings; currency: string; acceptingRequests: boolean };
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
export const getCatalog = () => boot.preview ? Promise.resolve(fallbackCatalog) : request<StoreCatalog>('/catalog');
export const submitRequest = (payload: RentalPayload) => request<RequestReceipt>('/requests', { method: 'POST', body: JSON.stringify(payload) });
