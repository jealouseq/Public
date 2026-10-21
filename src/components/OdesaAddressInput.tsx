import { useEffect, useId, useLayoutEffect, useRef, useState, type CSSProperties, type PointerEvent as ReactPointerEvent, type Ref } from 'react';
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
  const suggestionList = useRef<HTMLUListElement | null>(null);
  const optionPress = useRef<{ pointerId: number; targetId: string; x: number; y: number; scrollTop: number; moved: boolean; blurred: boolean; commit: () => void } | null>(null);
  const committedTouch = useRef<{ x: number; y: number; time: number } | null>(null);
  const refreshPlacement = useRef<(() => void) | null>(null);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const preferredSide = useRef<'above' | 'below' | null>(null);
  const [placement, setPlacement] = useState<{ side: 'above' | 'below'; top: number; height: number } | null>(null);
  const suggestions = findOdesaStreets(value, language);
  const completeStreet = hasCompleteOdesaStreet(value);
  const expanded = open && !completeStreet && suggestions.length > 0;
  const activeId = expanded && active >= 0 && active < suggestions.length ? `${id}-street-${suggestions[active].id}` : undefined;

  useEffect(() => { setActive(-1); setOpen(false); }, [language]);

  useEffect(() => {
    // A press stores its current commit callback; focus changes cannot remove
    // the target before pointerup, or leave these native listeners with stale copy.
    const finish = (event: PointerEvent) => {
      const press = optionPress.current;
      if (!press || press.pointerId !== event.pointerId) return;
      optionPress.current = null;
      const hit = document.elementFromPoint(event.clientX, event.clientY)?.closest('[role="option"]');
      const didScroll = Math.abs((suggestionList.current?.scrollTop ?? 0) - press.scrollTop) > 1;
      if (!press.moved && !didScroll && hit?.id === press.targetId) {
        event.preventDefault();
        // Removing the option before a touch click can retarget that click to
        // a control below the popup. Consume this gesture's click separately.
        if (event.pointerType === 'touch') committedTouch.current = { x: event.clientX, y: event.clientY, time: event.timeStamp };
        press.commit();
      } else if (press.blurred) { setOpen(false); setActive(-1); }
      refreshPlacement.current?.();
    };
    const move = (event: PointerEvent) => {
      const press = optionPress.current;
      if (press?.pointerId === event.pointerId && Math.hypot(event.clientX - press.x, event.clientY - press.y) > 8) press.moved = true;
    };
    const cancel = (event: PointerEvent) => {
      const press = optionPress.current;
      if (!press || press.pointerId !== event.pointerId) return;
      optionPress.current = null;
      if (press.blurred) { setOpen(false); setActive(-1); }
      refreshPlacement.current?.();
    };
    const resetTouchClick = () => { committedTouch.current = null; };
    const click = (event: MouseEvent) => {
      const committed = committedTouch.current;
      // detail:0 covers keyboard, VoiceOver and programmatic activation.
      if (!committed || event.detail === 0) return;
      committedTouch.current = null;
      const elapsed = event.timeStamp - committed.time;
      if (elapsed >= 0 && elapsed <= 1000 && Math.hypot(event.clientX - committed.x, event.clientY - committed.y) <= 8) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    };
    const leaveWindow = () => { resetTouchClick(); if (optionPress.current) { optionPress.current = null; setOpen(false); setActive(-1); } };
    window.addEventListener('pointerdown', resetTouchClick, true);
    window.addEventListener('keydown', resetTouchClick, true);
    window.addEventListener('click', click, true);
    window.addEventListener('pointerup', finish, true);
    window.addEventListener('pointermove', move, true);
    window.addEventListener('pointercancel', cancel, true);
    window.addEventListener('blur', leaveWindow);
    return () => {
      optionPress.current = null;
      committedTouch.current = null;
      window.removeEventListener('pointerdown', resetTouchClick, true);
      window.removeEventListener('keydown', resetTouchClick, true);
      window.removeEventListener('click', click, true);
      window.removeEventListener('pointerup', finish, true);
      window.removeEventListener('pointermove', move, true);
      window.removeEventListener('pointercancel', cancel, true);
      window.removeEventListener('blur', leaveWindow);
    };
  }, []);

  useLayoutEffect(() => {
    if (!expanded) { preferredSide.current = null; return; }
    let frame: number | null = null;
    const measure = () => {
      frame = null;
      if (optionPress.current) return;
      const input = field.current;
      if (!input?.parentElement) return;
      const bounds = input.getBoundingClientRect();
      const container = input.parentElement.getBoundingClientRect();
      const viewport = window.visualViewport;
      const viewportTop = viewport?.offsetTop ?? 0;
      const viewportBottom = viewportTop + (viewport?.height ?? document.documentElement.clientHeight);
      const spaces = {
        above: Math.max(0, bounds.top - viewportTop - 20),
        below: Math.max(0, viewportBottom - bounds.bottom - 20),
      };
      const desired = Math.min(320, suggestions.length * 48 + 45);
      let side = preferredSide.current;
      if (!side) side = spaces.below >= Math.min(desired, 180) || spaces.below >= spaces.above ? 'below' : 'above';
      else {
        const other = side === 'above' ? 'below' : 'above';
        // Keep the selected side through small scroll/keyboard adjustments.
        if (spaces[side] < Math.min(desired, 96) && spaces[other] >= spaces[side] + 48) side = other;
      }
      preferredSide.current = side;
      const next = {
        side,
        top: Math.round((side === 'above' ? bounds.top - 8 : bounds.bottom + 8) - container.top),
        height: Math.min(320, Math.floor(spaces[side])),
      };
      setPlacement(current => current?.side === next.side && current.top === next.top && current.height === next.height ? current : next);
    };
    const schedule = () => { if (frame === null) frame = requestAnimationFrame(measure); };
    refreshPlacement.current = schedule;
    measure();
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, true);
    window.visualViewport?.addEventListener('resize', schedule);
    window.visualViewport?.addEventListener('scroll', schedule);
    return () => {
      if (frame !== null) cancelAnimationFrame(frame);
      if (refreshPlacement.current === schedule) refreshPlacement.current = null;
      window.removeEventListener('resize', schedule);
      window.removeEventListener('scroll', schedule, true);
      window.visualViewport?.removeEventListener('resize', schedule);
      window.visualViewport?.removeEventListener('scroll', schedule);
    };
  }, [expanded, suggestions.length]);

  useLayoutEffect(() => {
    const list = suggestionList.current;
    const option = activeId ? document.getElementById(activeId) : null;
    if (!list || !option) return;
    const itemBounds = option.getBoundingClientRect();
    const listBounds = list.getBoundingClientRect();
    if (itemBounds.top < listBounds.top + 5) list.scrollTop += itemBounds.top - listBounds.top - 5;
    else if (itemBounds.bottom > listBounds.bottom - 5) list.scrollTop += itemBounds.bottom - listBounds.bottom + 5;
  }, [activeId, placement?.height]);

  function select(street: OdesaStreetSuggestion) {
    onChange(addressWithStreet(value, street.label));
    setOpen(false);
    setActive(-1);
    field.current?.focus({ preventScroll: true });
  }

  function startOptionPress(event: ReactPointerEvent<HTMLLIElement>, street: OdesaStreetSuggestion) {
    if (event.button !== 0 || event.isPrimary === false) return;
    event.preventDefault();
    optionPress.current = {
      pointerId: event.pointerId,
      targetId: event.currentTarget.id,
      x: event.clientX,
      y: event.clientY,
      scrollTop: suggestionList.current?.scrollTop ?? 0,
      moved: false,
      blurred: false,
      commit: () => select(street),
    };
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
        onBlur={() => { if (optionPress.current) optionPress.current.blurred = true; else { setOpen(false); setActive(-1); } }}
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
      {expanded && <div className={`address-suggestions${placement?.side === 'above' ? ' address-suggestions-above' : ''}`} style={{
        top: placement ? `${placement.top}px` : undefined,
        '--address-popup-height': placement ? `${placement.height}px` : '320px',
      } as CSSProperties}>
        <ul ref={suggestionList} id={`${id}-streets`} role="listbox" aria-label={t('Підказки вулиць Одеси', 'Подсказки улиц Одессы')}>
          {suggestions.map((street, index) => <li
            id={`${id}-street-${street.id}`}
            key={street.id}
            role="option"
            aria-selected={active === index}
            onPointerDown={event => startOptionPress(event, street)}
            onClick={event => { // VoiceOver/programmatic activation has no pointer gesture.
              if (event.detail === 0 || typeof window.PointerEvent === 'undefined') select(street);
            }}
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
