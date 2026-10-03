import { useEffect, useId, useRef, useState, type Ref } from 'react';
import { MapPin } from '@phosphor-icons/react';
import { useI18n } from '../lib/i18n';
import { addressWithStreet, findOdesaStreets, hasCompleteOdesaStreet, type OdesaStreetSuggestion } from '../lib/odesa-streets';
import '../address-input.css';

export function OdesaAddressInput({ value, onChange, inputRef }: {
  value: string;
  onChange: (value: string) => void;
  inputRef?: Ref<HTMLInputElement>;
}) {
  const { t, language } = useI18n();
  const id = useId();
  const field = useRef<HTMLInputElement | null>(null);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const suggestions = findOdesaStreets(value, language);
  const completeStreet = hasCompleteOdesaStreet(value);
  const expanded = open && !completeStreet && suggestions.length > 0;
  const activeId = expanded && active >= 0 && active < suggestions.length ? `${id}-street-${suggestions[active].id}` : undefined;

  useEffect(() => { setActive(-1); setOpen(false); }, [language]);

  function select(street: OdesaStreetSuggestion) {
    onChange(addressWithStreet(value, street.label));
    setOpen(false);
    setActive(-1);
    field.current?.focus({ preventScroll: true });
  }

  function setFieldRef(node: HTMLInputElement | null) {
    field.current = node;
    if (typeof inputRef === 'function') inputRef(node);
    else if (inputRef) inputRef.current = node;
  }

  return <div className="address-field odesa-address-field">
    <label htmlFor={`${id}-address`}><span className="field-label">{t('Адреса доставки', 'Адрес доставки')}</span></label>
    <div className="address-combobox">
      <input
        id={`${id}-address`}
        ref={setFieldRef}
        type="text"
        name="address"
        role="combobox"
        autoComplete="street-address"
        enterKeyHint="done"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded={expanded}
        aria-controls={expanded ? `${id}-streets` : undefined}
        aria-activedescendant={activeId}
        aria-describedby={`${id}-help`}
        placeholder={t('Вулиця, будинок, квартира', 'Улица, дом, квартира')}
        value={value}
        minLength={5}
        maxLength={300}
        required
        onFocus={() => setOpen(true)}
        onBlur={() => { setOpen(false); setActive(-1); }}
        onChange={event => { onChange(event.target.value); setActive(-1); setOpen(!(event.nativeEvent as InputEvent).isComposing); }}
        onCompositionEnd={() => setOpen(true)}
        onKeyDown={event => {
          if (event.nativeEvent.isComposing) return;
          if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && suggestions.length > 0 && !completeStreet) {
            event.preventDefault();
            setOpen(true);
            setActive(index => event.key === 'ArrowDown'
              ? (expanded ? index + 1 : 0) % suggestions.length
              : (expanded && index >= 0 ? index - 1 + suggestions.length : suggestions.length - 1) % suggestions.length);
          } else if (event.key === 'Enter' && expanded) {
            event.preventDefault();
            select(suggestions[active >= 0 && active < suggestions.length ? active : 0]);
          } else if (event.key === 'Enter') {
            event.preventDefault();
            field.current?.blur();
          } else if (event.key === 'Escape' && expanded) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(false);
            setActive(-1);
          } else if (event.key === 'Tab') {
            setOpen(false);
            setActive(-1);
          }
        }}
      />
      {expanded && <div className="address-suggestions">
        <ul id={`${id}-streets`} role="listbox" aria-label={t('Підказки вулиць Одеси', 'Подсказки улиц Одессы')}>
          {suggestions.map((street, index) => <li
            id={`${id}-street-${street.id}`}
            key={street.id}
            role="option"
            aria-selected={active === index}
            onPointerDown={event => { if (event.button === 0) event.preventDefault(); }}
            onClick={() => select(street)}
            onPointerMove={() => setActive(index)}
          ><MapPin size={17} weight="light" aria-hidden="true" /><span>{street.label}</span></li>)}
        </ul>
        <a className="address-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer" tabIndex={-1} onPointerDown={event => event.preventDefault()}>© OpenStreetMap contributors</a>
      </div>}
    </div>
    <span className="input-help" id={`${id}-help`}>{t('Можна обрати підказку або ввести адресу вручну.', 'Можно выбрать подсказку или ввести адрес вручную.')}</span>
    <span className="address-sr-status" role="status" aria-live="polite" aria-atomic="true">{expanded ? t(`Підказок: ${suggestions.length}. Обери стрілками та натисни Enter.`, `Подсказок: ${suggestions.length}. Выбери стрелками и нажми Enter.`) : ''}</span>
  </div>;
}
