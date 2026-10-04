import sharp from 'sharp';
import { copyFile, readFile, readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';

const directory = 'wordpress/joyrent/assets/images';
const manifest = JSON.parse(await readFile(join(directory, 'hero-media.json'), 'utf8'));
const names = await readdir(directory);
let total = 0, count = 0;

async function derivative(master, file, width, quality = 92) {
  const source = join(directory, master);
  const metadata = await sharp(source).metadata();
  if (!metadata.width || metadata.width < width) {
    throw new Error(`${file}: ${width}px exceeds native ${metadata.width || 0}px master ${master}; provide a real larger source.`);
  }
  const target = join(directory, file);
  await sharp(source).resize({ width, withoutEnlargement: true }).webp({ quality, effort: 6, alphaQuality: 100 }).toFile(target);
  total += (await stat(target)).size;
  count++;
}

// The same recipes drive the React picture and WordPress preload. Preserve the
// custom hero encoding rather than replacing it with a generic quality setting.
for (const [mode, fallbackMaster] of [['desktop', 'ps5-commercial-desktop-close.png'], ['mobile', 'ps5-commercial-mobile.png']]) {
  for (const source of manifest[mode].sources) {
    await derivative(source.master || manifest[mode].master || fallbackMaster, source.file, source.width, source.quality ?? (source.width < 1000 ? 89 : 92));
  }
}

for (const width of [560, 1120]) {
  await derivative('dualsense-cutout.png', `dualsense-cutout-${width}.webp`, width);
}

// Share crawlers get a small JPEG of existing hero artwork, with no composition
// change. The same recipe preserves this asset when responsive images regenerate.
const share = join(directory, 'ps5-share.jpg');
await sharp(join(directory, 'ps5-commercial-desktop-detail.png'))
  .resize({ width: 1200, withoutEnlargement: true })
  .jpeg({ quality: 85, mozjpeg: true }).toFile(share);
total += (await stat(share)).size;
count++;

// Legacy original paths remain available. Cards and details share -720.webp.
// Copy native 720px WebPs without recompressing them a second time.
for (const name of names.filter(name => /^(cover-|game-).+\.webp$/.test(name) && !/-(360|720)\.webp$/.test(name))) {
  const stem = name.replace(/\.webp$/, '');
  const master = names.includes(`${stem}.png`) ? `${stem}.png` : name;
  await derivative(master, `${stem}-360.webp`, 360, 90);
  const metadata = await sharp(join(directory, name)).metadata();
  if (metadata.width === 720) {
    const target = join(directory, `${stem}-720.webp`);
    await copyFile(join(directory, name), target);
    total += (await stat(target)).size;
    count++;
  } else {
    await derivative(master, `${stem}-720.webp`, 720);
  }
}
console.log(`Prepared ${count} native responsive images: ${(total / 1024 / 1024).toFixed(2)} MiB total. Original artwork preserved.`);
