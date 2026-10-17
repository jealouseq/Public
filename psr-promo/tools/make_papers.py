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

# 2 — traces: four evidence cards, fully visible
page("phone","ПАМЯТКА","""
.k{position:absolute;width:440px;height:296px;border-radius:16px;padding:24px 28px}
.k .t{font:600 24px Oswald;letter-spacing:.26em;color:var(--olive)}
.k1{left:72px;top:146px;transform:rotate(-1.8deg)}.k2{left:568px;top:136px;transform:rotate(1.6deg)}
.k3{left:72px;top:462px;transform:rotate(1.4deg)}.k4{left:568px;top:452px;transform:rotate(-1.5deg)}
.k svg{position:absolute;left:0;top:0;width:100%;height:100%}
.tp{position:absolute;width:128px;height:36px;background:rgba(214,196,142,.78);z-index:9}
""",
'''<div class="card k k1"><div class="t">ВИЗИТ</div><svg viewBox="0 0 440 296"><circle cx="220" cy="168" r="86" fill="none" stroke="#11150f" stroke-width="8"/><path d="M220 112v58l38 24" fill="none" stroke="#11150f" stroke-width="8" stroke-linecap="round"/><circle cx="220" cy="168" r="108" fill="none" stroke="#a8412b" stroke-width="5" stroke-dasharray="2 14" stroke-linecap="round"/></svg></div>
<div class="card k k2"><div class="t">ГОЛОСОВЫЕ</div><svg viewBox="0 0 440 296"><circle cx="86" cy="170" r="42" fill="#34441f"/><path d="M76 150l32 20-32 20z" fill="#f4ecd4"/><g id="wv"></g></svg></div>
<div class="card k k3"><div class="t">ФОТО</div><svg viewBox="0 0 440 296"><rect x="28" y="64" width="384" height="204" rx="6" fill="#fffaf0" stroke="rgba(17,21,15,.12)"/><rect x="42" y="78" width="356" height="176" fill="#cbd8b4"/><circle cx="318" cy="128" r="22" fill="#f2c46f"/><path d="M42 254V186l80-62 70 54 66-82 140 112v46z" fill="#34441f"/></svg></div>
<div class="card k k4"><div class="t">КОНТАКТЫ</div><svg viewBox="0 0 440 296"><g fill="#34441f"><circle cx="64" cy="124" r="26"/><circle cx="64" cy="190" r="26"/><circle cx="64" cy="256" r="26"/></g><g fill="#11150f"><rect x="110" y="112" width="250" height="16" rx="8"/><rect x="110" y="178" width="200" height="16" rx="8"/><rect x="110" y="244" width="230" height="16" rx="8"/></g><g fill="#aaa68c"><rect x="110" y="136" width="140" height="11" rx="5.5"/><rect x="110" y="202" width="110" height="11" rx="5.5"/><rect x="110" y="268" width="150" height="11" rx="5.5"/></g></svg></div>
<div class="tp" style="left:240px;top:448px;transform:rotate(-3deg)"></div><div class="tp" style="left:748px;top:122px;transform:rotate(4deg)"></div>
<script>let h="",s=11;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);for(let i=0;i<24;i++){const x=150+i*11.4,a=20+Math.abs(Math.sin(i*.5))*84*(.45+r()*.7);h+=`<rect x="${x.toFixed(0)}" y="${(170-a/2).toFixed(0)}" width="6.4" height="${a.toFixed(0)}" rx="3.2" fill="#11150f"/>`}document.getElementById("wv").innerHTML=h;</script>''',
"Следы","в телефоне.","Сохраните, пока ничего не удалилось.")

# 3 — route (v3): headline on top, three big rows, each with a rubber stamp as the marker
def stamp3(col,num,txt):
    return f'''<svg viewBox="-160 -160 320 320" width="196" height="196"><g filter="url(#st)" fill="none" stroke="{col}"><circle r="148" stroke-width="12"/><circle r="126" stroke-width="4"/><circle r="64" stroke-width="4"/><text font-family="Oswald" font-weight="600" font-size="27" letter-spacing="3" fill="{col}" stroke="none" text-anchor="middle"><textPath href="#c1" startOffset="50%">{txt}</textPath></text><text y="36" text-anchor="middle" font-family="Cormorant Garamond" font-weight="700" font-size="118" fill="{col}" stroke="none">{num}</text><text y="114" text-anchor="middle" font-family="Oswald" font-size="24" letter-spacing="8" fill="{col}" stroke="none">★ ★ ★</text></g></svg>'''
rows=[("#34441f",1,"ЧАСТЬ","Командование части"),("#34441f",2,"ВОЕНКОМАТ","Военкомат"),("#a8412b",3,"КОПИИ","Копии — себе")]
ROWS=''.join(f'<div class="rw" style="top:{504+i*244}px"><div class="sp" style="transform:rotate({[-7,5,-4][i]}deg)">{stamp3(c,n,t)}</div><div class="rt">{title}</div></div>' for i,(c,n,t,title) in enumerate(rows))
ARR=''.join(f'<svg class="ar" style="top:{504+i*244+202}px" width="26" height="30" viewBox="0 0 26 30"><path d="M13 0v18" stroke="#34441f" stroke-width="5" stroke-dasharray="1 9" stroke-linecap="round"/><path d="M3 18h20L13 30z" fill="#34441f"/></svg>' for i in range(2))
page("route","МАРШРУТ","""
h1{top:132px}.sub{top:414px}
.rw{position:absolute;left:72px;right:72px;height:200px;display:flex;align-items:center;gap:52px}
.sp{flex:none;width:196px;height:196px}
.rt{font:600 82px/1 'Cormorant Garamond',serif;letter-spacing:-.03em;color:var(--ink);white-space:nowrap}
.rw:nth-of-type(1) .rt{color:var(--ink)}
.ar{position:absolute;left:157px}
.line{position:absolute;left:72px;right:72px;height:2.5px;background:var(--ink)}
""",
'<svg width="0" height="0" style="position:absolute"><defs><filter id="st" x="-10%" y="-10%" width="120%" height="120%"><feTurbulence type="fractalNoise" baseFrequency=".035" numOctaves="2" seed="2" result="w"/><feDisplacementMap in="SourceGraphic" in2="w" scale="6" result="d"/><feTurbulence type="fractalNoise" baseFrequency=".8" numOctaves="2" seed="9" result="n"/><feColorMatrix in="n" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 -9 6.4" result="m"/><feComposite in="d" in2="m" operator="in"/></filter><path id="c1" d="M0 0m-100 0a100 100 0 1 1 200 0"/></defs></svg>'+ROWS+ARR,
"Официальный","путь.","Письменно — и копию оставьте себе.")

# 4 — care (v3): one big arched window with warm light + three words
page("care","ЗАБОТА","""
h1{top:132px}.sub{display:none}
.win{position:absolute;left:560px;top:456px;width:460px;height:764px}
.halo{position:absolute;left:470px;top:380px;width:640px;height:900px;background:radial-gradient(closest-side,rgba(242,196,111,.75),rgba(242,196,111,.24) 60%,transparent);filter:blur(10px)}
.wd{position:absolute;left:72px;top:560px;width:440px}
.wd div{font:600 112px/1 'Cormorant Garamond',serif;letter-spacing:-.03em;padding:34px 0 30px;border-top:2.5px solid var(--ink)}
.wd div:last-child{border-bottom:2.5px solid var(--ink);color:var(--rust)}
""",
'''<div class="halo"></div>
<svg class="win" viewBox="-12 -12 460 764"><defs>
<clipPath id="ar"><path d="M0 740V218A218 218 0 0 1 436 218V740Z"/></clipPath>
<linearGradient id="rm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff0c2"/><stop offset=".55" stop-color="#f6c872"/><stop offset="1" stop-color="#d98f3d"/></linearGradient>
<radialGradient id="lp" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fffbe6" stop-opacity=".95"/><stop offset="1" stop-color="#fff0c2" stop-opacity="0"/></radialGradient></defs>
<g clip-path="url(#ar)"><rect width="436" height="740" fill="url(#rm)"/>
 <circle cx="322" cy="330" r="190" fill="url(#lp)"/>
 <g fill="#b9772c" opacity=".55"><rect x="40" y="250" width="120" height="10"/><rect x="44" y="196" width="16" height="54"/><rect x="64" y="208" width="14" height="42"/><rect x="82" y="190" width="18" height="60"/><rect x="104" y="204" width="14" height="46"/></g>
 <path d="M278 360l22-62h56l22 62z" fill="#fff8de"/><path d="M328 360v200" stroke="#7a4f1c" stroke-width="9"/><ellipse cx="328" cy="566" rx="40" ry="9" fill="#7a4f1c"/>
 <rect x="0" y="548" width="436" height="192" fill="#8a5a1e"/><rect x="0" y="548" width="436" height="14" fill="#a8701f"/>
 <path d="M82 556h82l-10 64H92z" fill="#fff6dc"/><path d="M164 570c26 0 26 34 0 34" fill="none" stroke="#fff6dc" stroke-width="10"/>
 <path d="M104 536c-18-24 18-30 0-60M134 536c-18-24 18-30 0-60" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="6" stroke-linecap="round"/>
 <path d="M218 0v740M0 330h436" stroke="#1d2615" stroke-width="14"/></g>
<path d="M0 740V218A218 218 0 0 1 436 218V740Z" fill="none" stroke="#1d2615" stroke-width="22"/>
<rect x="-14" y="716" width="464" height="24" fill="#1d2615"/></svg>
<div class="wd"><div>Сон.</div><div>Еда.</div><div>Рядом.</div></div>''',
"Берегите себя,","пока ждёте.","")

# 5 — listen: a big voice message, a calm reply and listening waves
page("listen","ДИАЛОГ","""
.v{left:72px;right:72px;top:150px;height:330px;border-radius:56px 56px 12px 56px;background:linear-gradient(160deg,#5b7e44,#34441f);box-shadow:0 34px 70px rgba(52,68,31,.4);display:flex;align-items:center;gap:36px;padding:0 54px}
.pl{flex:none;width:150px;height:150px;border-radius:50%;background:#f6eed6;display:grid;place-items:center;box-shadow:0 10px 24px rgba(0,0,0,.25)}
.pl svg{width:60px;height:60px;margin-left:10px}
.wf{flex:1;height:220px}
.du{flex:none;font:600 46px Oswald;letter-spacing:.08em;color:#f6eed6}
.rp{left:72px;top:540px;padding:38px 62px;border-radius:50px 50px 50px 12px;background:var(--card);border:3.5px solid var(--ink);font:800 84px/1.05 Manrope;letter-spacing:-.025em;box-shadow:0 28px 56px rgba(70,56,20,.2)}
svg.wv{position:absolute;left:700px;top:520px;width:320px;height:230px}
""",
'''<div class="v card"><div class="pl"><svg viewBox="0 0 60 60"><path d="M8 4l48 26L8 56z" fill="#34441f"/></svg></div><svg class="wf" id="wf" viewBox="0 0 520 220"></svg><div class="du">2:14</div></div>
<div class="rp card">Мы слушаем.</div>
<svg class="wv" viewBox="0 0 320 230" fill="none" stroke="#34441f" stroke-linecap="round" stroke-width="9"><path d="M60 62a70 70 0 0 1 0 106" opacity=".95"/><path d="M110 28a118 118 0 0 1 0 174" opacity=".6"/><path d="M162 -6a166 166 0 0 1 0 242" opacity=".3"/></svg>
<script>let h="",s=17;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);for(let i=0;i<36;i++){const e=Math.sin(i/35*Math.PI)*.6+.4,a=20+e*170*(.35+r()*.65),x=i*14.4;h+=`<rect x="${x.toFixed(1)}" y="${(110-a/2).toFixed(0)}" width="9.5" height="${a.toFixed(0)}" rx="4.75" fill="${i<22?'#f6eed6':'rgba(246,238,214,.38)'}"/>`}h+=`<circle cx="${(22*14.4+4).toFixed(0)}" cy="110" r="10" fill="#f2c46f"/>`;document.getElementById("wf").innerHTML=h;</script>''',
"Сначала —","выслушаем.","Расскажите то, что знаете.")

print("ok")
