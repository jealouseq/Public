// node tools/motion_render.js page OUT_DIR from to [workers]   (frames 1080x1350 JPG)
const http = require('http'), fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..');
const [PAGE, OUT, A, B, NW] = process.argv.slice(2);
const MIME = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2' };
(async () => {
  const s = http.createServer((rq, rs) => { const p = path.join(ROOT, decodeURIComponent(rq.url.split('?')[0])); if (!fs.existsSync(p) || fs.statSync(p).isDirectory()) { rs.writeHead(404); return rs.end(); } rs.writeHead(200, { 'Content-Type': MIME[path.extname(p)] || 'application/octet-stream' }); fs.createReadStream(p).pipe(rs); }).listen(0, '127.0.0.1');
  await new Promise((r) => s.on('listening', r));
  const b = await chromium.launch({ args: ['--no-sandbox', '--disable-gpu', '--force-color-profile=srgb'] });
  fs.mkdirSync(OUT, { recursive: true });
  const frames = []; for (let i = +A; i <= +B; i++) frames.push(i);
  let next = 0, done = 0; const t0 = Date.now();
  await Promise.all(Array.from({ length: +(NW || 4) }, async () => {
    const pg = await b.newPage({ viewport: { width: 1080, height: 1350 } });
    pg.on('pageerror', (e) => console.error('[pageerror]', e.message));
    await pg.goto(`http://127.0.0.1:${s.address().port}/${PAGE}`); await pg.waitForFunction('window.READY === true', null, { timeout: 60000 });
    for (;;) { const k = next++; if (k >= frames.length) break; const i = frames[k]; const d = await pg.evaluate((i) => window.frame(i, 0.95), i); fs.writeFileSync(path.join(OUT, `f_${String(i).padStart(4, '0')}.jpg`), Buffer.from(d.split(',')[1], 'base64')); if (++done % 30 === 0) console.log(done, '/', frames.length, ((Date.now() - t0) / 1000).toFixed(0) + 's'); }
  }));
  await b.close(); s.close();
})().catch((e) => { console.error(e); process.exit(1); });
