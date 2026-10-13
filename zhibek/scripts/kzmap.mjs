import { readFileSync, writeFileSync } from 'fs';
import * as topojson from 'topojson-client';
import { geoConicConformal, geoPath, geoContains } from 'd3-geo';

const topo = JSON.parse(readFileSync('./node_modules/world-atlas/countries-50m.json', 'utf8'));
const countries = topojson.feature(topo, topo.objects.countries);
const kz = countries.features.find((f) => f.properties.name === 'Kazakhstan');
if (!kz) throw new Error('Kazakhstan not found');

const W = 1000, H = 560;
const proj = geoConicConformal().rotate([-67, 0]).parallels([43, 55]).fitExtent([[18, 18], [W - 18, H - 18]], kz);
const path = geoPath(proj).digits(1);
const d = path(kz);

const cities = {
  'Алматы': [76.945, 43.238], 'Астана': [71.449, 51.169], 'Шымкент': [69.587, 42.315],
  'Караганда': [73.085, 49.806], 'Актобе': [57.167, 50.283], 'Атырау': [51.903, 47.107],
  'Актау': [51.15, 43.65], 'Усть-Каменогорск': [82.628, 49.948], 'Павлодар': [76.967, 52.287],
  'Костанай': [63.627, 53.214], 'Тараз': [71.378, 42.9], 'Уральск': [51.367, 51.233],
  'Кызылорда': [65.51, 44.85], 'Семей': [80.227, 50.411], 'Туркестан': [68.252, 43.297],
  'Петропавловск': [69.15, 54.867], 'Кокшетау': [69.378, 53.285], 'Талдыкорган': [78.373, 45.017],
};
const out = {};
for (const [n, ll] of Object.entries(cities)) { const p = proj(ll); out[n] = [Math.round(p[0] * 10) / 10, Math.round(p[1] * 10) / 10]; }

// dot grid inside the border (for a dotted-map look) – generated as one compact path of tiny circles
const step = 9, dots = [];
for (let y = 24; y < H; y += step) for (let x = 24 + ((Math.round(y / step) % 2) * step / 2); x < W; x += step) {
  const ll = proj.invert([x, y]); if (ll && geoContains(kz, ll)) dots.push([x, y]);
}
console.log('outline chars', d.length, 'dots', dots.length);
writeFileSync(process.argv[2], JSON.stringify({ w: W, h: H, d, cities: out, dots }));
