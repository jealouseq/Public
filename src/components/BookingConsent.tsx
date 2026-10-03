import { useId } from 'react';
import { useI18n } from '../lib/i18n';

export function BookingConsent({ checked, onChange, onLegal }: {
  checked: boolean; onChange: (checked: boolean) => void;
  onLegal: (page: 'privacy' | 'terms') => void;
}) {
  const { t } = useI18n();
  const id = useId();
  return <div className="consent">
    <label className="consent-check" htmlFor={`${id}-check`}>
      <input id={`${id}-check`} type="checkbox" checked={checked} onChange={event => onChange(event.target.checked)} aria-labelledby={`${id}-copy`} required />
    </label>
    <span className="consent-copy" id={`${id}-copy`}>
      <label htmlFor={`${id}-check`}>{t('Погоджуюся з ', 'Соглашаюсь с ')}</label>
      <button type="button" onClick={() => onLegal('terms')}>{t('умовами оренди', 'условиями аренды')}</button>
      {' '}<label htmlFor={`${id}-check`}>{t('та ', 'и ')}</label>
      <button type="button" onClick={() => onLegal('privacy')}>{t('обробкою персональних даних', 'обработкой персональных данных')}</button>.
    </span>
  </div>;
}
