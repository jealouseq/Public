#!/usr/bin/env node
'use strict';
/*
  Deterministic frame renderer.
    node tools/render.js still --times 0.5,3,7 --out /path/dir [--w 1080 --h 1080]
    node tools/render.js seq --out /path/frames [--workers 4] [--from 0 --to 959] [--q 0.95]
  Needs: NODE_PATH pointing at a global playwright install.
*/
const http = require('http'), fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..');
const TL = require(path.join(ROOT, 'src/timeline.js'));
const args = process.argv.slice(2), cmd = args.shift();
const opt = {}; for (let i = 0; i < args.length; i += 2) opt[args[i].replace(/^--/, '')] = args[i + 1];
const W = +(opt.w || 1080), H = +(opt.h || 1080), OUT = opt.out || path.join(ROOT, 'out/frames');
const MIME = { '.html': 'text/html', '.js': 'text/javascript', '.json': 'application/json', '.css': 'text/css', '.woff2': 'font/woff2', '.u8': 'application/octet-stream', '.i16': 'application/octet-stream' };

function serve() {
  return new Promise((res) => {
    const s = http.createServer((rq, rs) => {
      const p = path.join(ROOT, decodeURIComponent(rq.url.split('?')[0]));
      if (!p.startsWith(ROOT) || !fs.existsSync(p) || fs.statSync(p).isDirectory()) { rs.writeHead(404); return rs.end(); }
      rs.writeHead(200, { 'Content-Type': MIME[path.extname(p)] || 'application/octet-stream', 'Content-Length': fs.statSync(p).size });
      fs.createReadStream(p).pipe(rs);
    }).listen(0, '127.0.0.1', () => res({ s, port: s.address().port }));
  });
}
async function openPage(browser, port) {
  const page = await browser.newPage({ viewport: { width: W, height: H } });
  page.on('pageerror', (e) => console.error('[pageerror]', e.message));
  page.on('console', (m) => { if (m.type() === 'error') console.error('[console]', m.text()); });
  await page.goto(`http://127.0.0.1:${port}/src/index.html?w=${W}&h=${H}`);
  await page.waitForFunction('window.READY === true', null, { timeout: 180000 });
  return page;
}
async function main() {
  const { s, port } = await serve();
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-gpu', '--force-color-profile=srgb'] });
  const q = +(opt.q || 0.95);
  if (cmd === 'still') {
    const page = await openPage(browser, port);
    for (const ts of (opt.times || '0').split(',')) {
      const t = +ts, i = Math.round(t * TL.fps);
      const t0 = Date.now();
      const d = await page.evaluate(([i, q]) => window.renderFrame(i, q), [i, q]);
      fs.writeFileSync(path.join(OUT, `t_${t.toFixed(2).padStart(6, '0')}.jpg`), Buffer.from(d.split(',')[1], 'base64'));
      console.log('t', t, 'ms', Date.now() - t0);
    }
  } else if (cmd === 'seq') {
    const total = Math.round(TL.total * TL.fps), from = +(opt.from || 0), to = Math.min(total - 1, +(opt.to === undefined ? total - 1 : opt.to));
    const nw = +(opt.workers || 4);
    const frames = []; for (let i = from; i <= to; i++) frames.push(i);
    let next = 0, done = 0; const t0 = Date.now();
    await Promise.all(Array.from({ length: nw }, async () => {
      const page = await openPage(browser, port);
      for (;;) {
        const k = next++; if (k >= frames.length) break;
        const i = frames[k];
        const d = await page.evaluate(([i, q]) => window.renderFrame(i, q), [i, q]);
        fs.writeFileSync(path.join(OUT, `f_${String(i).padStart(5, '0')}.jpg`), Buffer.from(d.split(',')[1], 'base64'));
        if (++done % 30 === 0) console.log(`${done}/${frames.length}  ${((Date.now() - t0) / 1000).toFixed(0)}s`);
      }
    }));
    console.log('done', frames.length, 'frames in', ((Date.now() - t0) / 1000).toFixed(0), 's');
  }
  await browser.close(); s.close();
}
main().catch((e) => { console.error(e); process.exit(1); });
