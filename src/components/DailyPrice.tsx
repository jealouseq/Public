import { dailyRate, money } from '../lib/rental';
import { useI18n } from '../lib/i18n';

export function DailyPrice({ price, days, className = '' }: { price: number; days: number; className?: string }) {
  const { t } = useI18n();
  const rate = dailyRate(price, days);
  if (!rate) return null;
  return <p className={`daily-price ${className}`}>
    {rate.approximate && <><span aria-hidden="true">≈ </span><span className="sr-only">{t('Приблизно ', 'Примерно ')}</span></>}
    {money(rate.amount)} {t('грн/день', 'грн/день')}
  </p>;
}
