import sharp from 'sharp';
import { readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';

const directory = 'wordpress/joyrent/assets/images';
let total = 0;
for (const name of await readdir(directory)) {
  if (!name.endsWith('.png')) continue;
  const source = join(directory, name);
  const target = source.replace(/\.png$/, '.webp');
  const width = name.startsWith('game-') ? 720 : name === 'favicon.png' ? 64 : 1680;
  await sharp(source).resize({ width, withoutEnlargement: true }).webp({ quality: 86, effort: 5 }).toFile(target);
  total += (await stat(target)).size;
}
console.log(`Optimized local images: ${(total / 1024 / 1024).toFixed(2)} MiB total.`);
