import { useId } from 'react';
import { FileText, ShieldCheck } from '@phosphor-icons/react';
import { money } from '../lib/rental';
import type { SecurityMode } from '../lib/draft';
import { useI18n } from '../lib/i18n';

export function SecurityChoice({ value, deposit, onChange }: { value: SecurityMode; deposit: number | null; onChange: (value: SecurityMode) => void }) {
  const { t } = useI18n();
  const id = useId();
  return <fieldset className="security-field" aria-describedby={`${id}-help`}>
    <legend className="field-label">{t('Оформлення оренди', 'Оформление аренды')}</legend>
    <div className="security-choices">
      <label className={`security-choice${value === 'deposit' ? ' selected' : ''}`}>
        <input type="radio" name={`${id}-security`} value="deposit" checked={value === 'deposit'} onChange={() => onChange('deposit')} />
        <ShieldCheck size={22} weight="light" aria-hidden="true" />
        <span><strong>{t('Із заставою', 'С залогом')}</strong><span>{deposit === null ? t('Суму узгодимо', 'Сумму согласуем') : `${money(deposit)} грн`} · {t('повертається', 'возвращается')}</span></span>
      </label>
      <label className={`security-choice${value === 'contract' ? ' selected' : ''}`}>
        <input type="radio" name={`${id}-security`} value="contract" checked={value === 'contract'} onChange={() => onChange('contract')} />
        <FileText size={22} weight="light" aria-hidden="true" />
        <span><strong>{t('За договором', 'По договору')}</strong><span>{t('Без застави після перевірки документів', 'Без залога после проверки документов')}</span></span>
      </label>
    </div>
    <p id={`${id}-help`} className="security-help">{value === 'contract'
      ? t('Для договору потрібні паспорт, ІПН і реєстрація. Деталі узгодимо після заявки.', 'Для договора нужны паспорт, ИНН и регистрация. Детали согласуем после заявки.')
      : t('Заставу повернемо після повернення та перевірки комплекту.', 'Залог вернём после возврата и проверки комплекта.')}</p>
  </fieldset>;
}
