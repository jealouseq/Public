import { mkdir, readdir, stat, chmod, copyFile, readFile, writeFile, rm, cp } from 'node:fs/promises';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
const root = process.cwd();
const { version } = JSON.parse(await readFile(join(root,'package.json'),'utf8'));
const pluginHeader = await readFile(join(root,'wordpress/joyrent-rentals/joyrent-rentals.php'),'utf8');
const pluginVersion = pluginHeader.match(/^\s*\*?\s*Version:\s*([\d.]+)/m)?.[1];
if (!pluginVersion) throw new Error('Rental plugin version is missing.');
const theme = join(root, 'wordpress/joyrent');
const release = join(root, 'releases');
const stage = join(root, 'work/package');
async function permissions(dir) {
  await chmod(dir, 0o755);
  for (const name of await readdir(dir)) {
    const path = join(dir, name);
    if ((await stat(path)).isDirectory()) await permissions(path);
    else await chmod(path, 0o644);
  }
}
await mkdir(join(theme, 'licenses'), { recursive: true });
for (const font of ['manrope','unbounded','onest']) await copyFile(join(root, `node_modules/@fontsource-variable/${font}/LICENSE`), join(theme, `licenses/${font}-OFL.txt`));
await permissions(join(root, 'wordpress'));
await mkdir(release, { recursive: true });
await rm(stage, { recursive: true, force: true });
await mkdir(stage, { recursive: true });
await cp(theme, join(stage, 'joyrent'), { recursive: true, filter: path => !path.endsWith('.png') || path.endsWith('/screenshot.png') });
await cp(join(root, 'wordpress/joyrent-rentals'), join(stage, 'joyrent-rentals'), { recursive: true });
for (const name of ['joyrent','joyrent-rentals']) {
  const file = join(release, `${name}-${name==='joyrent-rentals'?pluginVersion:version}.zip`);
  await rm(file, { force: true });
  execFileSync('zip', ['-qr',file,name], { cwd: stage });
}
const preview = join(root, 'preview');
await rm(preview, { recursive: true, force: true });
await mkdir(preview, { recursive: true });
await cp(join(theme,'assets/dist/assets'),join(preview,'assets'),{recursive:true});
await cp(join(theme,'assets/images'),join(preview,'images'),{recursive:true,filter:path=>!path.endsWith('.png')});
const manifest=JSON.parse(await readFile(join(theme,'assets/dist/.vite/manifest.json'),'utf8'))['src/main.tsx'];
const styles=manifest.css.map(file=>`<link rel="stylesheet" href="./${file}">`).join('\n');
await writeFile(join(preview,'index.html'),`<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#08090b"><title>JOYRENT — візуальний перегляд</title>${styles}</head><body><div id="joyrent-root"></div><script>window.JOYRENT={apiBase:'./api-preview',assetBase:'.',preview:true};</script><script type="module" src="./${manifest.file}"></script></body></html>`);
await writeFile(join(preview,'README.txt'),'Visual preview only. Requests require WordPress + WooCommerce. Run: python3 -m http.server 4173 inside this folder, then open http://localhost:4173.\n');
const faq=JSON.parse(await readFile(join(root,'wordpress/joyrent-rentals/data/faq.json'),'utf8'));
const escapeHTML=value=>value.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
for (const language of ['uk','ru']) {
  const ru=language==='ru';
  const home=ru?'./?lang=ru':'./';
  const title=ru?'Вопросы об аренде':'Питання про оренду';
  const choose=ru?'Выбрать даты':'Обрати дати';
  const answers=faq[language].map(({question,answer})=>`<details><summary>${escapeHTML(question)}</summary><p>${escapeHTML(answer)}</p></details>`).join('');
  await writeFile(join(preview,ru?'faq-ru.html':'faq.html'),`<!doctype html><html lang="${language}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title} — JOYRENT</title>${styles}</head><body><header class="site-header"><div class="shell header-inner"><a class="wordmark" href="${home}">JOYRENT<span class="brand-dot">.</span></a><div class="native-header-actions"><nav class="native-language-switch"><a href="./faq.html" lang="uk" ${ru?'':'aria-current="page"'}>UA</a><a href="./faq-ru.html" lang="ru" ${ru?'aria-current="page"':''}>RU</a></nav><a class="button button-outline" href="${home}#booking">${choose}</a></div></div></header><main class="shell faq-page"><a class="faq-back" href="${home}">${ru?'На главную':'На головну'}</a><div class="faq-page-layout"><div><p class="eyebrow">FAQ</p><h1>${title}</h1><p class="faq-intro">${ru?'Что подготовить, как получить консоль и что важно знать перед арендой.':'Що підготувати, як отримати консоль і що важливо знати перед орендою.'}</p></div><div><div class="faq-list">${answers}</div><div class="faq-page-actions"><a class="button button-light" href="${home}#booking">${choose}</a></div></div></div></main><footer class="site-footer"><div class="shell native-footer-links"><a href="${home}">JOYRENT</a><a href="${home}#booking">${choose}</a></div></footer></body></html>`);
}
await permissions(preview);
await rm(join(release,`joyrent-preview-${version}.zip`),{force:true});
execFileSync('zip',['-qr',join(release,`joyrent-preview-${version}.zip`),'.'],{cwd:preview});
console.log('Packaged WordPress theme, rental plugin and static visual preview.');
