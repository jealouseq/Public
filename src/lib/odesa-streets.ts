import type { Language } from './i18n';

export interface OdesaStreetSuggestion { id: string; label: string }

// Bounded, offline street suggestions, not a geocoder or a delivery-zone check.
// Ukrainian names and OSM way IDs are from the checked-in extract retrieved
// 2026-10-03: wordpress/joyrent/assets/data/delivery-basemap-osm.geojson.gz.
// © OpenStreetMap contributors, ODbL 1.0: https://www.openstreetmap.org/copyright
// Russian strings are localized search/display aliases, not official renamings.
// Source query and license: docs/refinement-1.8/delivery-basemap-source.md.
const streets: Array<{ osmId: number; uk: string; ru: string }> = [
  { osmId: 26543701, uk: 'Фонтанська дорога', ru: 'Фонтанская дорога' },
  { osmId: 26532984, uk: 'Люстдорфська дорога', ru: 'Люстдорфская дорога' },
  { osmId: 26498191, uk: 'Французький бульвар', ru: 'Французский бульвар' },
  { osmId: 26519455, uk: 'Канатна вулиця', ru: 'Канатная улица' },
  { osmId: 25481581, uk: 'Генуезька вулиця', ru: 'Генуэзская улица' },
  { osmId: 31503950, uk: 'Тіниста вулиця', ru: 'Тенистая улица' },
  { osmId: 31537477, uk: 'Архітекторська вулиця', ru: 'Архитекторская улица' },
  { osmId: 26544524, uk: 'вулиця Академіка Корольова', ru: 'улица Академика Королёва' },
  { osmId: 26532018, uk: 'вулиця Академіка Філатова', ru: 'улица Академика Филатова' },
  { osmId: 55894661, uk: 'вулиця Академіка Заболотного', ru: 'улица Академика Заболотного' },
  { osmId: 26546089, uk: 'вулиця Академіка Сахарова', ru: 'улица Академика Сахарова' },
  { osmId: 26546151, uk: 'Марсельська вулиця', ru: 'Марсельская улица' },
  { osmId: 52698934, uk: 'вулиця Семена Палія', ru: 'улица Семёна Палия' },
  { osmId: 25481570, uk: 'проспект Шевченка', ru: 'проспект Шевченко' },
  { osmId: 55903351, uk: 'проспект Небесної Сотні', ru: 'проспект Небесной Сотни' },
  { osmId: 31524040, uk: 'проспект Свободи', ru: 'проспект Свободы' },
  { osmId: 122192845, uk: 'Адміральський проспект', ru: 'Адмиральский проспект' },
  { osmId: 26532014, uk: 'Варненська вулиця', ru: 'Варненская улица' },
  { osmId: 31508602, uk: 'вулиця Костанді', ru: 'улица Костанди' },
  { osmId: 28803107, uk: 'вулиця Левітана', ru: 'улица Левитана' },
  { osmId: 31478450, uk: 'Педагогічна вулиця', ru: 'Педагогическая улица' },
  { osmId: 26436921, uk: 'Сегедська вулиця', ru: 'Сегедская улица' },
  { osmId: 26518163, uk: 'Середньофонтанська вулиця', ru: 'Среднефонтанская улица' },
  { osmId: 26523661, uk: 'Академічна вулиця', ru: 'Академическая улица' },
  { osmId: 8256182, uk: 'Пироговська вулиця', ru: 'Пироговская улица' },
  { osmId: 103621282, uk: 'Семінарська вулиця', ru: 'Семинарская улица' },
  { osmId: 4423502, uk: 'Пантелеймонівська вулиця', ru: 'Пантелеймоновская улица' },
  { osmId: 28803405, uk: 'Успенська вулиця', ru: 'Успенская улица' },
  { osmId: 143604550, uk: 'Велика Арнаутська вулиця', ru: 'Большая Арнаутская улица' },
  { osmId: 27228650, uk: 'Мала Арнаутська вулиця', ru: 'Малая Арнаутская улица' },
  { osmId: 38074163, uk: 'Рішельєвська вулиця', ru: 'Ришельевская улица' },
  { osmId: 28880159, uk: 'Преображенська вулиця', ru: 'Преображенская улица' },
  { osmId: 28849730, uk: 'Ланжеронівська вулиця', ru: 'Ланжероновская улица' },
  { osmId: 103616271, uk: 'Єврейська вулиця', ru: 'Еврейская улица' },
  { osmId: 26496758, uk: 'Грецька вулиця', ru: 'Греческая улица' },
  { osmId: 8256199, uk: 'Троїцька вулиця', ru: 'Троицкая улица' },
  { osmId: 26518151, uk: 'Софіївська вулиця', ru: 'Софиевская улица' },
  { osmId: 27227976, uk: 'Ніжинська вулиця', ru: 'Нежинская улица' },
  { osmId: 4423489, uk: 'Балківська вулиця', ru: 'Балковская улица' },
  { osmId: 28882093, uk: 'Мʼясоєдовська вулиця', ru: 'Мясоедовская улица' },
  { osmId: 28882313, uk: 'Прохоровська вулиця', ru: 'Прохоровская улица' },
  { osmId: 56124222, uk: 'Разумовська вулиця', ru: 'Разумовская улица' },
  { osmId: 28851570, uk: 'Мельницька вулиця', ru: 'Мельницкая улица' },
  { osmId: 82939986, uk: 'Бугаївська вулиця', ru: 'Бугаевская улица' },
  { osmId: 4423404, uk: 'Миколаївська дорога', ru: 'Николаевская дорога' },
  { osmId: 28851623, uk: 'Овідіопольська дорога', ru: 'Овидиопольская дорога' },
  { osmId: 31520846, uk: 'вулиця Дача Ковалевського', ru: 'улица Дача Ковалевского' },
  { osmId: 31514158, uk: 'Довга вулиця', ru: 'Долгая улица' },
];

const streetTypes = new Set(['вулиця', 'улица', 'вул', 'ул', 'дорога', 'проспект', 'просп', 'бульвар', 'бул', 'провулок', 'переулок', 'пер']);

function normalizedName(value: string): string {
  return value.toLocaleLowerCase().replace(/ё/g, 'е').replace(/['ʼ’`]/g, '')
    .replace(/[.,]/g, ' ').split(/\s+/).filter(word => word && !streetTypes.has(word)).join(' ');
}

function normalizedFullName(value: string): string {
  return value.trim().toLocaleLowerCase().replace(/ё/g, 'е').replace(/['ʼ’`]/g, '')
    .replace(/\s+/g, ' ');
}

function splitAddress(value: string): { street: string; suffix: string } {
  const trimmed = value.trim();
  const boundary = trimmed.search(/,|\s+(?=\d|(?:будинок|буд\.?|дом|д\.|кв\.?|квартира)(?:\s|\.|$))/i);
  return boundary === -1 ? { street: trimmed, suffix: '' } : {
    street: trimmed.slice(0, boundary).trim(),
    suffix: trimmed.slice(boundary).replace(/^[,\s]+/, ''),
  };
}

export function findOdesaStreets(value: string, language: Language, limit = 6): OdesaStreetSuggestion[] {
  const query = normalizedName(splitAddress(value).street);
  if (query.length < 2) return [];
  const words = query.split(' ');
  return streets.map(street => {
    const aliases = [normalizedName(street.uk), normalizedName(street.ru)];
    const matches = aliases.filter(alias => words.every(word => alias.includes(word)));
    const score = matches.some(alias => alias.startsWith(query)) ? 0 : 1;
    return { street, matches: matches.length > 0, score };
  }).filter(result => result.matches).sort((a, b) => a.score - b.score)
    .slice(0, Math.max(0, Math.min(8, limit)))
    .map(({ street }) => ({ id: `osm-way-${street.osmId}`, label: street[language] }));
}

export function addressWithStreet(value: string, label: string): string {
  const { suffix } = splitAddress(value);
  return suffix ? `${label}, ${suffix}` : label;
}

export function hasCompleteOdesaStreet(value: string): boolean {
  const streetName = normalizedFullName(splitAddress(value).street);
  return streets.some(street => [street.uk, street.ru].some(name => normalizedFullName(name) === streetName));
}
