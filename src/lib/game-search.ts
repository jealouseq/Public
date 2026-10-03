function normalize(value: string) {
  return value.normalize('NFKD').replace(/\p{M}/gu, '').toLocaleLowerCase().replace(/[.’'ʼ]/g, '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
}

/** Local title search also recognizes common names visitors use for familiar series. */
export function matchesGameQuery(title: string, query: string): boolean {
  let haystack = normalize(title);
  if (/\bea sports fc\b/.test(haystack)) haystack += ' fifa фіфа фифа';
  if (haystack.includes('grand theft auto')) haystack += ' gta гта';
  if (haystack.includes('mortal kombat')) haystack += ' mk мортал комбат';
  return normalize(query).split(/\s+/).filter(Boolean).every(word => haystack.includes(word));
}
