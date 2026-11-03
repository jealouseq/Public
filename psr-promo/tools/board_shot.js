// node tools/board_shot.js src/storyboard.html OUT.png [pdf]
const http=require('http'),fs=require('fs'),path=require('path');const {chromium}=require('playwright');
const ROOT=path.resolve(__dirname,'..');const [PAGE,OUT,PDF]=process.argv.slice(2);
const MIME={'.html':'text/html','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.jpg':'image/jpeg','.png':'image/png'};
(async()=>{const s=http.createServer((rq,rs)=>{const p=path.join(ROOT,decodeURIComponent(rq.url.split('?')[0]));if(!fs.existsSync(p)||fs.statSync(p).isDirectory()){rs.writeHead(404);return rs.end()}rs.writeHead(200,{'Content-Type':MIME[path.extname(p)]||'application/octet-stream'});fs.createReadStream(p).pipe(rs)}).listen(0,'127.0.0.1');
await new Promise(r=>s.on('listening',r));const b=await chromium.launch({args:['--no-sandbox','--force-color-profile=srgb']});
const pg=await b.newPage({viewport:{width:1800,height:1200},deviceScaleFactor:1});pg.on('pageerror',e=>console.error('[pageerror]',e.message));
await pg.goto(`http://127.0.0.1:${s.address().port}/${PAGE}`);await pg.evaluate(()=>document.fonts.ready);await pg.waitForTimeout(1200);
const el=await pg.$('#board');const box=await el.boundingBox();console.log('board',box.width,box.height);
await el.screenshot({path:OUT});
if(PDF){await pg.pdf({path:PDF,width:'1800px',height:Math.ceil(box.height)+'px',printBackground:true,pageRanges:'1'})}
await b.close();s.close()})().catch(e=>{console.error(e);process.exit(1)});
