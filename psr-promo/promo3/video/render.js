// node promo3/video/render.js OUTDIR W H [fps] [from] [to] [workers] [times=comma list for stills]
const http=require('http'),fs=require('fs'),path=require('path');const {chromium}=require('playwright');
const ROOT=path.resolve(__dirname,'../..');
const [OUT,W,H,FPS,FROM,TO,NW,TIMES]=process.argv.slice(2);
const MIME={'.html':'text/html','.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.jpg':'image/jpeg','.png':'image/png'};
(async()=>{
 const s=http.createServer((rq,rs)=>{const p=path.join(ROOT,decodeURIComponent(rq.url.split('?')[0]));if(!fs.existsSync(p)||fs.statSync(p).isDirectory()){rs.writeHead(404);return rs.end()}rs.writeHead(200,{'Content-Type':MIME[path.extname(p)]||'application/octet-stream'});fs.createReadStream(p).pipe(rs)}).listen(0,'127.0.0.1');
 await new Promise(r=>s.on('listening',r));const port=s.address().port;
 const b=await chromium.launch({args:['--no-sandbox','--disable-gpu','--force-color-profile=srgb']});
 fs.mkdirSync(OUT,{recursive:true});
 const open=async()=>{const pg=await b.newPage({viewport:{width:+W,height:+H}});pg.on('pageerror',e=>console.error('[pageerror]',e.message));await pg.goto(`http://127.0.0.1:${port}/promo3/video/index.html?w=${W}&h=${H}`);await pg.waitForFunction('window.READY===true',null,{timeout:120000});return pg};
 const fps=+(FPS||30);
 let jobs=[];
 if(TIMES){jobs=TIMES.split(',').map(t=>({t:+t,name:`still_${(+t).toFixed(2)}.jpg`,f:Math.round(+t*fps)}))}
 else{const pg0=await open();const dur=await pg0.evaluate('window.DUR');await pg0.close();const total=Math.ceil(dur*fps),from=+(FROM||0),to=Math.min(total-1,+(TO===undefined||TO===''?total-1:TO));for(let i=from;i<=to;i++)jobs.push({t:i/fps,name:`f_${String(i).padStart(5,'0')}.jpg`,f:i});console.log('frames',jobs.length,'dur',dur)}
 let next=0,done=0;const t0=Date.now();
 await Promise.all(Array.from({length:+(NW||4)},async()=>{const pg=await open();for(;;){const k=next++;if(k>=jobs.length)break;const j=jobs[k];await pg.evaluate(([t,f])=>window.setT(t,f),[j.t,j.f]);await pg.screenshot({path:path.join(OUT,j.name),type:'jpeg',quality:94});if(++done%60===0)console.log(done,'/',jobs.length,((Date.now()-t0)/1000).toFixed(0)+'s')}await pg.close()}));
 await b.close();s.close();console.log('done',jobs.length);
})().catch(e=>{console.error(e);process.exit(1)});
