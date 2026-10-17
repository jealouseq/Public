#!/usr/bin/env python3
"""Five light-paper static posts (series). python3 -I tools/make_papers.py -> src/paper_*.html"""
import os
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CSS="""
:root{--ink:#11150f;--paper:#ebe2ca;--olive:#34441f;--rust:#a8412b;--card:#f7f1de;--m:72px}
*{box-sizing:border-box;margin:0;padding:0}html,body{background:#000}
.post{width:1080px;height:1350px;position:relative;overflow:hidden;background:radial-gradient(120% 100% at 30% 8%,#f4edd8,var(--paper) 55%,#ddd3b6);color:var(--ink);font-family:Manrope,sans-serif}
.top{position:absolute;left:var(--m);right:var(--m);top:56px;display:flex;justify-content:space-between;font:600 21px Oswald;letter-spacing:.24em;padding-bottom:24px;border-bottom:2.5px solid var(--ink)}
.top span:last-child{color:var(--rust)}
h1{position:absolute;left:var(--m);top:778px;margin-left:-5px;font:600 132px/.98 'Cormorant Garamond',serif;letter-spacing:-.035em}
h1 span{display:block;white-space:nowrap}
h1 u{text-decoration:none;position:relative;display:inline-block;isolation:isolate}
h1 u:after{content:"";position:absolute;left:-4px;right:-4px;bottom:8px;height:17px;background:var(--rust);opacity:.88;transform:skewX(-12deg);z-index:-1}
.sub{position:absolute;left:var(--m);top:1074px;width:860px;font:500 36px/1.4 Manrope;color:#2a3020}
.foot{position:absolute;left:var(--m);right:var(--m);bottom:50px;padding-top:22px;border-top:2.5px solid var(--ink);font:600 20px Oswald;letter-spacing:.22em;color:var(--olive)}
.tex{position:absolute;inset:0;mix-blend-mode:multiply;opacity:.5;pointer-events:none}
.card{position:absolute;background:var(--card);box-shadow:0 2px 0 rgba(255,255,255,.7) inset,0 26px 60px rgba(70,56,20,.22),0 3px 8px rgba(70,56,20,.16)}
"""
TEX='<svg class="tex" width="1080" height="1350"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency=".75" numOctaves="3" seed="3"/><feColorMatrix values="0 0 0 0 .55  0 0 0 0 .5  0 0 0 0 .4  0 0 0 .5 -.08"/></filter><rect width="100%" height="100%" filter="url(#n)"/></svg>'
def page(name,tag,extra_css,hero,l1,l2,sub):
    html=f"""<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>ЦПР — {name}</title>
<link rel="stylesheet" href="/fonts/fonts.css"><link rel="stylesheet" href="/fonts/post.css"><link rel="stylesheet" href="/fonts/post2.css">
<style>{CSS}{extra_css}</style></head><body><div class="post" id="post">
<div class="top"><span>ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ</span><span>{tag}</span></div>
{hero}
<h1><span>{l1}</span><span><u>{l2}</u></span></h1>
<div class="sub">{sub}</div>
<div class="foot">КОНТАКТЫ — В ОПИСАНИИ КАНАЛА</div>{TEX}</div></body></html>"""
    open(f"{ROOT}/src/paper_{name}.html","w").write(html)


# 1 — red flags: huge note, hand-drawn marker loops measured from the real text lines
FLAGS_JS = """document.fonts.ready.then(()=>{let s=5;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);const note=document.getElementById("note");let out="";let n=0;document.querySelectorAll(".m").forEach(sp=>{n++;[...sp.getClientRects()].forEach((q,i)=>{const pad=14,cx=q.left+q.width/2,cy=q.top+q.height/2,rx=q.width/2+pad+8,ry=q.height/2+5;let d="";const N=36,rot=(r()-.5)*.05;for(let k=0;k<=N+4;k++){const t=k/N*Math.PI*2+Math.PI*1.15,j=1+Math.sin(k*.7)*.012+(r()-.5)*.012,gr=1+0.045*(k/N);const x=cx+Math.cos(t)*rx*j*gr,y=cy+Math.sin(t)*ry*j*gr+rot*(x-cx);d+=(k?"L":"M")+x.toFixed(1)+" "+y.toFixed(1)}out+=`<path d="${d}" fill="none" stroke="#a8412b" stroke-width="5.5" stroke-linecap="round" stroke-linejoin="round" opacity=".92"/>`;})});document.getElementById("mk").innerHTML=out;});"""
page("flags","ВНИМАНИЕ","""
.msg{left:60px;top:150px;width:960px;height:590px;padding:48px 60px;transform:rotate(-1.2deg);border-radius:4px}
.msg small{display:block;font:600 22px Oswald;letter-spacing:.24em;color:#7b7a64;margin-bottom:22px}
.msg p{font:500 58px/1.56 Manrope;color:#1d2216;letter-spacing:-.01em}
.m{font-weight:800;color:#0f130c}
.msg:before{content:"";position:absolute;left:50%;top:-20px;width:140px;height:40px;margin-left:-70px;background:rgba(214,196,142,.72);transform:rotate(-2deg)}
svg.mk{position:absolute;inset:0;pointer-events:none;overflow:visible}
""",
'<div class="card msg" id="note"><small>НЕЗНАКОМЫЙ НОМЕР</small><p>Здравствуйте! Мы <span class="m">гарантированно найдём</span> вашего бойца. Решать нужно <span class="m">срочно, сегодня</span>. У нас <span class="m">закрытый доступ</span> к данным.</p></div><svg class="mk" id="mk" width="1080" height="1350"></svg><script>'+FLAGS_JS+'</script>',
"Три тревожных","знака.","Честная помощь так не говорит.")

# 2 — traces: a fan of evidence cards
page("phone","ПАМЯТКА","""
.fan{position:absolute;left:0;top:0;width:1080px;height:780px}
.c{position:absolute;top:196px;width:340px;height:480px;transform-origin:50% 100%;border-radius:22px;background:var(--card);box-shadow:0 2px 0 rgba(255,255,255,.8) inset,0 30px 60px rgba(70,56,20,.24),0 4px 10px rgba(70,56,20,.16);padding:30px;overflow:hidden}
.c .t{position:absolute;left:28px;top:26px;font:600 24px Oswald;letter-spacing:.24em;color:var(--olive)}
.c svg{width:100%;margin-top:46px}
.c1{left:60px;top:218px;transform:rotate(-8deg)}.c2{left:267px;top:196px;transform:rotate(-2.5deg)}.c3{left:473px;top:196px;transform:rotate(2.5deg)}.c4{left:680px;top:218px;transform:rotate(8deg)}
.tp{position:absolute;left:50%;top:178px;width:130px;height:38px;margin-left:-65px;background:rgba(214,196,142,.75);transform:rotate(-3deg);z-index:9}
""",
'''<div class="fan">
<div class="c c1"><svg viewBox="0 0 312 380"><circle cx="156" cy="150" r="104" fill="none" stroke="#11150f" stroke-width="8"/><path d="M156 84v70l44 28" fill="none" stroke="#11150f" stroke-width="9" stroke-linecap="round"/><circle cx="156" cy="150" r="124" fill="none" stroke="#a8412b" stroke-width="5" stroke-dasharray="3 14" stroke-linecap="round"/></svg><div class="t">ВИЗИТ</div></div>
<div class="c c2"><svg viewBox="0 0 312 380"><circle cx="64" cy="124" r="44" fill="#34441f"/><path d="M52 104l34 20-34 20z" fill="#f4ecd4"/><g id="wv"></g></svg><div class="t">ГОЛОСОВЫЕ</div></div>
<div class="c c3"><svg viewBox="0 0 312 380"><rect x="0" y="40" width="312" height="250" rx="12" fill="#cfd9b8"/><circle cx="226" cy="108" r="26" fill="#f2c46f"/><path d="M0 290V200l70-60 60 50 60-76 122 100v76z" fill="#34441f"/></svg><div class="t">ФОТО</div></div>
<div class="c c4"><svg viewBox="0 0 312 380"><g fill="#34441f"><circle cx="46" cy="70" r="30"/><circle cx="46" cy="160" r="30"/><circle cx="46" cy="250" r="30"/></g><g fill="#11150f"><rect x="96" y="56" width="190" height="16" rx="8"/><rect x="96" y="146" width="150" height="16" rx="8"/><rect x="96" y="236" width="176" height="16" rx="8"/></g><g fill="#a9a58a"><rect x="96" y="84" width="110" height="12" rx="6"/><rect x="96" y="174" width="90" height="12" rx="6"/><rect x="96" y="264" width="120" height="12" rx="6"/></g></svg><div class="t">КОНТАКТЫ</div></div>
<div class="tp"></div></div>
<script>let h="",s=11;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);for(let i=0;i<22;i++){const x=22+i*13.2,a=18+Math.abs(Math.sin(i*.55))*70*(.5+r()*.8);h+=`<rect x="${x}" y="${(250-a/2).toFixed(0)}" width="7" height="${a.toFixed(0)}" rx="3.5" fill="#11150f"/>`}document.getElementById("wv").innerHTML=h;</script>''',
"Следы","в телефоне.","Сохраните, пока ничего не удалилось.")

# 3 — route: three big rubber stamps joined by a dashed path
def stamp(x,y,rot,col,txt,num):
    return f'''<g transform="translate({x} {y}) rotate({rot})" fill="none" stroke="{col}"><circle r="150" stroke-width="9"/><circle r="132" stroke-width="3"/><circle r="70" stroke-width="3"/><text font-family="Oswald" font-weight="600" font-size="23" letter-spacing="3" fill="{col}" stroke="none" text-anchor="middle"><textPath href="#c1" startOffset="50%">{txt}</textPath></text><text y="32" text-anchor="middle" font-family="Cormorant Garamond" font-weight="700" font-size="108" fill="{col}" stroke="none">{num}</text><text y="116" text-anchor="middle" font-family="Oswald" font-size="24" letter-spacing="8" fill="{col}" stroke="none">★ ★ ★</text></g>'''
page("route","МАРШРУТ","""
svg.rt{position:absolute;left:0;top:110px;width:1080px;height:660px}
""",
'''<svg class="rt" viewBox="0 0 1080 660"><defs>
<filter id="st" x="-10%" y="-10%" width="120%" height="120%"><feTurbulence type="fractalNoise" baseFrequency=".035" numOctaves="2" seed="2" result="w"/><feDisplacementMap in="SourceGraphic" in2="w" scale="5" result="d"/><feTurbulence type="fractalNoise" baseFrequency=".8" numOctaves="2" seed="9" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 -9 6.4" result="m"/><feComposite in="d" in2="m" operator="in"/></filter>
<marker id="ar" markerWidth="14" markerHeight="14" refX="10" refY="7" orient="auto"><path d="M1 1l11 6-11 6" fill="none" stroke="#34441f" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></marker>
<path id="c1" d="M0 0m-108 0a108 108 0 1 1 216 0"/></defs>
<path d="M330 120C460 90 400 250 440 290" fill="none" stroke="#34441f" stroke-width="5" stroke-dasharray="2 16" stroke-linecap="round" marker-end="url(#ar)"/>
<path d="M690 330C790 330 760 440 800 470" fill="none" stroke="#34441f" stroke-width="5" stroke-dasharray="2 16" stroke-linecap="round" marker-end="url(#ar)"/>
<g filter="url(#st)">'''+stamp(190,180,-9,"#34441f","КОМАНДОВАНИЕ ЧАСТИ",1)+stamp(560,400,6,"#34441f","ВОЕННЫЙ КОМИССАРИАТ",2)+stamp(884,548,-5,"#a8412b","КОПИИ — СЕБЕ",3)+'''</g></svg>''',
"Официальный","путь.","Письменно — и копию оставьте себе.")

# 4 — care: a night facade, one lit window
page("care","ЗАБОТА","""
.top{z-index:5;color:#e8e0c6;border-bottom-color:rgba(232,224,198,.55)}.top span:last-child{color:#f2c46f}
svg.fc{position:absolute;left:0;top:0;width:1080px;height:790px;-webkit-mask-image:linear-gradient(180deg,#000 90%,transparent);mask-image:linear-gradient(180deg,#000 90%,transparent)}
""",
'''<svg class="fc" viewBox="0 0 1080 790"><defs>
<linearGradient id="fb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0d120d"/><stop offset="1" stop-color="#151c12"/></linearGradient>
<radialGradient id="gl" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ffd98f" stop-opacity=".85"/><stop offset=".35" stop-color="#f2c46f" stop-opacity=".3"/><stop offset="1" stop-color="#f2c46f" stop-opacity="0"/></radialGradient>
<linearGradient id="wl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff2c8"/><stop offset="1" stop-color="#f0b45a"/></linearGradient>
<filter id="gr"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="4"/><feColorMatrix values="0 0 0 0 .5  0 0 0 0 .5  0 0 0 0 .5  0 0 0 .5 -.1"/></filter></defs>
<rect width="1080" height="790" fill="url(#fb)"/><g id="wins"></g>
<circle cx="745" cy="445" r="470" fill="url(#gl)" style="mix-blend-mode:screen"/>
<g transform="translate(670 350)"><rect width="150" height="190" fill="url(#wl)"/>
 <rect x="0" y="146" width="150" height="44" fill="#6a4410" opacity=".55"/>
 <path d="M104 100l8-22h22l8 22z" fill="#fff6dc" opacity=".95"/><path d="M123 100v46" stroke="#3a2a10" stroke-width="4"/>
 <path d="M46 118h36l-5 28H51z" fill="#2c2412"/><path d="M82 124c10 0 10 14 0 14" fill="none" stroke="#2c2412" stroke-width="4"/>
 <path d="M58 106c-8-10 8-14 0-26M70 106c-8-10 8-14 0-26" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="3" stroke-linecap="round"/>
 <path d="M75 0v190M0 95h150" stroke="#161b11" stroke-width="7"/><rect width="150" height="190" fill="none" stroke="#161b11" stroke-width="9"/></g>
<rect width="1080" height="790" filter="url(#gr)" opacity=".35"/></svg>
<script>let h="",sd=3;const r=()=>((sd=(sd*1664525+1013904223)>>>0)/4294967296);for(let j=0;j<3;j++)for(let i=0;i<5;i++){const x=55+i*205,y=130+j*220;if(i===3&&j===1)continue;const v=r();const f=v<.1?"#2a2f22":(v<.18?"#243038":"#1b2216");h+=`<rect x="${x}" y="${y}" width="150" height="190" fill="${f}"/><path d="M${x+75} ${y}v190M${x} ${y+95}h150" stroke="#0b0f0a" stroke-width="7"/><rect x="${x}" y="${y}" width="150" height="190" fill="none" stroke="#0b0f0a" stroke-width="9"/>`}document.getElementById("wins").innerHTML=h;</script>''',
"Берегите себя,","пока ждёте.","Сон. Еда. Близкий человек рядом.")

# 5 — listen: a huge voice message and a calm reply
page("listen","ДИАЛОГ","""
.v{left:72px;right:72px;top:184px;height:300px;border-radius:52px 52px 12px 52px;background:linear-gradient(160deg,#587a41,#34441f);box-shadow:0 30px 60px rgba(52,68,31,.38);display:flex;align-items:center;gap:36px;padding:0 52px}
.pl{flex:none;width:136px;height:136px;border-radius:50%;background:#f4ecd4;display:grid;place-items:center}
.pl svg{width:56px;height:56px;margin-left:8px}
.wf{flex:1;height:170px}
.du{flex:none;font:600 44px Oswald;letter-spacing:.08em;color:#f4ecd4}
.rp{left:72px;top:540px;padding:34px 52px;border-radius:44px 44px 44px 12px;background:var(--card);border:3px solid var(--ink);font:800 66px/1.1 Manrope;letter-spacing:-.02em;box-shadow:0 26px 54px rgba(70,56,20,.18)}
""",
'''<div class="v card"><div class="pl"><svg viewBox="0 0 56 56"><path d="M8 4l44 24L8 52z" fill="#34441f"/></svg></div><svg class="wf" id="wf" viewBox="0 0 520 170"></svg><div class="du">2:14</div></div>
<div class="rp card">Мы слушаем.</div>
<script>let h="",s=17;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);for(let i=0;i<34;i++){const e=Math.sin(i/33*Math.PI)*.65+.35,a=18+e*130*(.35+r()*.65);h+=`<rect x="${i*15.5}" y="${(85-a/2).toFixed(0)}" width="9" height="${a.toFixed(0)}" rx="4.5" fill="${i<20?'#f4ecd4':'rgba(244,236,212,.4)'}"/>`}document.getElementById("wf").innerHTML=h;</script>''',
"Сначала —","выслушаем.","Расскажите то, что знаете.")
print("ok")
