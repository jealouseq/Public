// node tools/post_render.js OUT.jpg W H '{"t":3.6,"dy":-0.04}'
const http = require('http'), fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..');
const [OUT, W, H, J] = process.argv.slice(2); const o = JSON.parse(J || '{}');
const MIME = { '.html': 'text/html', '.js': 'text/javascript', '.json': 'application/json', '.css': 'text/css', '.woff2': 'font/woff2' };
(async () => {
  const s = http.createServer((rq, rs) => { const p = path.join(ROOT, decodeURIComponent(rq.url.split('?')[0])); if (!fs.existsSync(p) || fs.statSync(p).isDirectory()) { rs.writeHead(404); return rs.end(); } rs.writeHead(200, { 'Content-Type': MIME[path.extname(p)] || 'application/octet-stream' }); fs.createReadStream(p).pipe(rs); }).listen(0, '127.0.0.1');
  await new Promise((r) => s.on('listening', r));
  const b = await chromium.launch({ args: ['--no-sandbox', '--disable-gpu', '--force-color-profile=srgb'] });
  const pg = await b.newPage({ viewport: { width: +W, height: +H } });
  pg.on('pageerror', (e) => console.error('[pageerror]', e.message));
  await pg.goto(`http://127.0.0.1:${s.address().port}/src/post.html?w=${W}&h=${H}`);
  await pg.waitForFunction('window.READY === true', null, { timeout: 180000 });
  const d = await pg.evaluate(([o]) => window.renderPost(o, 0.95), [o]);
  fs.mkdirSync(path.dirname(OUT), { recursive: true }); fs.writeFileSync(OUT, Buffer.from(d.split(',')[1], 'base64')); console.log('wrote', OUT);
  await b.close(); s.close();
})().catch((e) => { console.error(e); process.exit(1); });
