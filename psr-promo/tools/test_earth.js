#!/usr/bin/env node
// quick look-dev: node tools/test_earth.js <outdir>   (renders a set of Earth test shots)
const http=require('http'),fs=require('fs'),path=require('path'); const {chromium}=require('playwright');
const ROOT=path.resolve(__dirname,'..'), OUT=process.argv[2]||'/tmp/earth_tests'; fs.mkdirSync(OUT,{recursive:true});
const MIME={'.html':'text/html','.js':'text/javascript','.json':'application/json','.css':'text/css','.woff2':'font/woff2'};
const shots=JSON.parse(fs.readFileSync(process.argv[3]||path.join(__dirname,'earth_shots.json'),'utf8'));
(async()=>{
  const s=http.createServer((rq,rs)=>{const p=path.join(ROOT,decodeURIComponent(rq.url.split('?')[0]));if(!fs.existsSync(p)||fs.statSync(p).isDirectory()){rs.writeHead(404);return rs.end();}rs.writeHead(200,{'Content-Type':MIME[path.extname(p)]||'application/octet-stream','Content-Length':fs.statSync(p).size});fs.createReadStream(p).pipe(rs);}).listen(0,'127.0.0.1');
  await new Promise(r=>s.on('listening',r)); const port=s.address().port;
  const b=await chromium.launch({args:['--no-sandbox','--disable-gpu']}); const pg=await b.newPage({viewport:{width:1080,height:1080}});
  pg.on('pageerror',e=>console.error('[pageerror]',e.message)); pg.on('console',m=>{if(m.type()==='error')console.error('[console]',m.text());});
  await pg.goto(`http://127.0.0.1:${port}/src/test_earth.html`); await pg.waitForFunction('window.READY===true',null,{timeout:180000});
  for(const [name,o] of Object.entries(shots)){ const t0=Date.now(); const d=await pg.evaluate(o=>window.renderTest(o),o); fs.writeFileSync(path.join(OUT,name+'.jpg'),Buffer.from(d.split(',')[1],'base64')); console.log(name,Date.now()-t0,'ms'); }
  await b.close(); s.close();
})().catch(e=>{console.error(e);process.exit(1)});
