// node promo3/shot.js page out.png [width] [height|full]
const http=require('http'),fs=require('fs'),path=require('path');const {chromium}=require('playwright');
const ROOT=path.resolve(__dirname,'..');const [PAGE,OUT,W,H]=process.argv.slice(2);
const MIME={'.html':'text/html','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.jpg':'image/jpeg','.png':'image/png','.json':'application/json'};
(async()=>{const s=http.createServer((rq,rs)=>{const p=path.join(ROOT,decodeURIComponent(rq.url.split('?')[0]));if(!fs.existsSync(p)||fs.statSync(p).isDirectory()){rs.writeHead(404);return rs.end()}rs.writeHead(200,{'Content-Type':MIME[path.extname(p)]||'application/octet-stream'});fs.createReadStream(p).pipe(rs)}).listen(0,'127.0.0.1');
await new Promise(r=>s.on('listening',r));const b=await chromium.launch({args:['--no-sandbox','--force-color-profile=srgb']});
const pg=await b.newPage({viewport:{width:+(W||1700),height:+(H&&H!=='full'?H:1000)},deviceScaleFactor:1});pg.on('pageerror',e=>console.error('[pageerror]',e.message));
await pg.goto(`http://127.0.0.1:${s.address().port}/${PAGE}`);await pg.evaluate(()=>document.fonts.ready);await pg.waitForTimeout(900);
await pg.screenshot({path:OUT,fullPage:true});await b.close();s.close();console.log('wrote',OUT)})().catch(e=>{console.error(e);process.exit(1)});
