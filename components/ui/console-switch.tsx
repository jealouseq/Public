import { useMotionPreference } from '../../src/lib/motion';
import { motion } from 'framer-motion';
import { useI18n } from '../../src/lib/i18n';
import type { ConsoleId } from '../../src/lib/rental';

export function ConsoleSwitch({ value, onChange, id }: { value: ConsoleId; onChange: (value: ConsoleId) => void; id: string }) {
  const reduced = useMotionPreference();
  const { t } = useI18n();
  return <div className="console-switch" role="group" aria-label={t('Обрати консоль', 'Выбрать консоль')}>
    {(['ps5', 'ps4'] as const).map(consoleId => <button key={consoleId} type="button" aria-pressed={value === consoleId} onClick={() => onChange(consoleId)}>
      {value === consoleId && <motion.span className="switch-active" layoutId={`console-${id}`} transition={{ type: 'spring', stiffness: reduced ? 1000 : 450, damping: 35, duration: reduced ? 0 : undefined }} />}
      <span>{consoleId.toUpperCase()}</span>
    </button>)}
  </div>;
}
