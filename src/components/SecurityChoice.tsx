import { useId } from 'react';
import { Check, FileText, ShieldCheck } from '@phosphor-icons/react';
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
        <span className="security-choice-content">
          <span className="security-choice-heading"><ShieldCheck size={22} weight="light" aria-hidden="true" /><strong>{t('Із заставою', 'С залогом')}</strong><span className="security-choice-indicator" aria-hidden="true">{value === 'deposit' && <Check size={13} weight="bold" />}</span></span>
          <span className="security-choice-details">
            <span><span className="security-detail-label">{t('Застава', 'Залог')}</span><span className="security-detail-value">{deposit === null ? t('Суму узгодимо', 'Сумму согласуем') : `${money(deposit)} грн`}</span></span>
            <span><span className="security-detail-label">{t('Повернення', 'Возврат')}</span><span className="security-detail-value">{t('Після перевірки комплекту', 'После проверки комплекта')}</span></span>
          </span>
        </span>
      </label>
      <label className={`security-choice${value === 'contract' ? ' selected' : ''}`}>
        <input type="radio" name={`${id}-security`} value="contract" checked={value === 'contract'} onChange={() => onChange('contract')} />
        <span className="security-choice-content">
          <span className="security-choice-heading"><FileText size={22} weight="light" aria-hidden="true" /><strong>{t('За договором', 'По договору')}</strong><span className="security-choice-indicator" aria-hidden="true">{value === 'contract' && <Check size={13} weight="bold" />}</span></span>
          <span className="security-choice-details">
            <span><span className="security-detail-label">{t('Застава', 'Залог')}</span><span className="security-detail-value">{t('Не потрібна після перевірки', 'Не нужен после проверки')}</span></span>
            <span><span className="security-detail-label">{t('Документи', 'Документы')}</span><span className="security-detail-value">{t('Паспорт, ІПН, реєстрація', 'Паспорт, ИНН, регистрация')}</span></span>
          </span>
        </span>
      </label>
    </div>
    <p id={`${id}-help`} className="security-help">{value === 'contract'
      ? t('Оформлення без застави можливе після особистої перевірки документів. Умови узгодимо під час підтвердження бронювання.', 'Оформление без залога возможно после личной проверки документов. Условия согласуем при подтверждении брони.')
      : t('Застава сплачується окремо від вартості оренди та повертається після перевірки комплекту.', 'Залог оплачивается отдельно от стоимости аренды и возвращается после проверки комплекта.')}</p>
  </fieldset>;
}
