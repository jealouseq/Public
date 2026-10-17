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

# 1 — red flags: a scam message with three marked phrases
page("flags","ВНИМАНИЕ","""
.msg{left:72px;top:172px;width:936px;height:540px;padding:44px 56px;transform:rotate(-1.1deg);border-radius:6px}
.msg small{display:block;font:600 22px Oswald;letter-spacing:.22em;color:#7b7a64;margin-bottom:26px}
.msg p{font:500 49px/1.62 Manrope;color:#1d2216}
.m{box-decoration-break:clone;-webkit-box-decoration-break:clone;border:3.5px solid var(--rust);border-radius:34px;padding:2px 14px;margin:0 -6px;transform:rotate(-.8deg);display:inline;color:#12160f;font-weight:700}
.msg:before{content:"";position:absolute;left:50%;top:-18px;width:130px;height:38px;margin-left:-65px;background:rgba(214,196,142,.7);transform:rotate(-2deg)}
""",
'<div class="card msg"><small>НЕЗНАКОМЫЙ НОМЕР</small><p>Здравствуйте! Мы <span class="m">гарантированно найдём</span> вашего бойца. Решать нужно <span class="m">срочно, сегодня</span>. У нас <span class="m">закрытый доступ</span> к данным.</p></div>',
"Три тревожных","знака.","Честная помощь так не говорит.")

# 2 — traces in the phone
page("phone","ПАМЯТКА","""
.ph{position:absolute;left:250px;top:166px;width:580px;height:640px;border:4.5px solid var(--ink);border-bottom:none;border-radius:84px 84px 0 0;background:var(--card);box-shadow:0 30px 70px rgba(70,56,20,.22);-webkit-mask:linear-gradient(180deg,#000 82%,transparent 100%);mask:linear-gradient(180deg,#000 82%,transparent 100%);padding:46px 40px}
.ph:before{content:"";position:absolute;left:50%;top:18px;width:120px;height:14px;margin-left:-60px;border-radius:8px;background:var(--ink)}
.who{display:flex;align-items:center;gap:20px;padding:22px 0 26px;border-bottom:2px solid rgba(17,21,15,.2);margin-top:12px}
.av{width:64px;height:64px;border-radius:50%;border:2.5px solid var(--ink)}
.who b{display:block;font:700 30px Manrope}.who small{display:block;font:500 23px Manrope;color:#6c6b58;margin-top:2px}
.it{display:flex;align-items:center;gap:22px;height:92px;border-bottom:2px solid rgba(17,21,15,.14);font:700 33px Manrope}
.it i{flex:none;width:56px;height:56px;border-radius:50%;background:var(--olive);display:grid;place-items:center}
.it svg{width:30px;height:30px;fill:none;stroke:#f4ecd4;stroke-width:2.6;stroke-linecap:round;stroke-linejoin:round}
.it.hl span{border:3.5px solid var(--rust);border-radius:30px;padding:0 14px;margin-left:-14px;transform:rotate(-.8deg)}
""",
'''<div class="ph"><div class="who"><div class="av"></div><div><b>Не в сети</b><small>был(а) давно</small></div></div>
<div class="it hl"><i><svg viewBox="0 0 30 30"><circle cx="15" cy="15" r="10"/><path d="M15 9v7l4 3"/></svg></i><span>Последний визит</span></div>
<div class="it"><i><svg viewBox="0 0 30 30"><rect x="11" y="4" width="8" height="14" rx="4"/><path d="M7 14a8 8 0 0 0 16 0M15 22v4"/></svg></i><span>Голосовые</span></div>
<div class="it"><i><svg viewBox="0 0 30 30"><rect x="4" y="6" width="22" height="18" rx="3"/><circle cx="11" cy="13" r="2.4"/><path d="M5 22l7-6 5 4 4-3 5 4"/></svg></i><span>Фото и видео</span></div>
<div class="it"><i><svg viewBox="0 0 30 30"><circle cx="11" cy="11" r="4.5"/><path d="M3 25c1-6 5-8 8-8s7 2 8 8M20 8a4 4 0 0 1 0 8M23 18c2 1 3 3 4 6"/></svg></i><span>Контакты сослуживцев</span></div></div>''',
"Следы","в телефоне.","Сохраните, пока ничего не удалилось.")

# 3 — official route (metro-style)
page("route","МАРШРУТ","""
.line{position:absolute;left:150px;top:246px;width:12px;height:376px;background:var(--olive);border-radius:6px}
.line:after{content:"";position:absolute;left:4px;top:0;bottom:0;width:4px;background:var(--paper);opacity:.55}
.st{position:absolute;left:72px;display:flex;align-items:center;gap:44px}
.st i{flex:none;width:96px;height:96px;border-radius:50%;background:var(--card);border:9px solid var(--olive);box-shadow:0 12px 30px rgba(70,56,20,.2);display:grid;place-items:center;position:relative;z-index:2}
.st i b{font:600 36px Oswald;color:var(--olive)}
.st span{font:600 92px/1 'Cormorant Garamond',serif;letter-spacing:-.025em}
.st.end i{background:var(--rust);border-color:var(--rust)}.st.end i b{color:#f6ecd2}
""",
'''<div class="line"></div>
<div class="st" style="top:196px"><i><b>1</b></i><span>Командование части</span></div>
<div class="st" style="top:382px"><i><b>2</b></i><span>Военкомат</span></div>
<div class="st end" style="top:568px"><i><b>3</b></i><span>Копии — себе</span></div>''',
"Официальный","путь.","Письменно — и копию оставьте себе.")

# 4 — care: one lit window in the night
page("care","ЗАБОТА","""
.night{left:72px;top:168px;width:936px;height:560px;background:linear-gradient(180deg,#151b12,#0e120c);border-radius:6px;overflow:hidden;box-shadow:0 30px 70px rgba(40,36,16,.35)}
.night svg{position:absolute;inset:0}
""",
'''<div class="card night"><svg viewBox="0 0 936 560">
<defs><radialGradient id="lg" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ffd98f" stop-opacity=".75"/><stop offset=".4" stop-color="#f2c46f" stop-opacity=".22"/><stop offset="1" stop-color="#f2c46f" stop-opacity="0"/></radialGradient>
<linearGradient id="wl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff0c4"/><stop offset="1" stop-color="#f0b45a"/></linearGradient></defs>
<g id="w"></g>
<circle cx="560" cy="300" r="300" fill="url(#lg)"/>
<rect x="520" y="258" width="82" height="108" rx="3" fill="url(#wl)"/>
<path d="M561 258v108M520 312h82" stroke="#1a1f14" stroke-width="5"/>
<path d="M531 366c4-34 20-40 31-40" stroke="#6a4a1a" stroke-width="3" fill="none" opacity=".0"/>
</svg></div>
<script>
let h='';const cols=9,rows=5,W=82,Hh=108,gx=(936-cols*W)/(cols+1),gy=(560-rows*Hh)/(rows+1);
let s=7;const r=()=>((s=(s*1664525+1013904223)>>>0)/4294967296);
for(let j=0;j<rows;j++)for(let i=0;i<cols;i++){const x=gx+i*(W+gx),y=gy+j*(Hh+gy);const lit=r()<.12;
 if(Math.abs(x-520)<4&&Math.abs(y-258)<4)continue;
 h+=`<rect x="${x.toFixed(0)}" y="${y.toFixed(0)}" width="${W}" height="${Hh}" rx="3" fill="${lit?'#2b2a1c':'#1b2216'}"/><path d="M${(x+W/2).toFixed(0)} ${y.toFixed(0)}v${Hh}" stroke="#0e120c" stroke-width="4"/>`;}
document.getElementById('w').innerHTML=h;</script>''',
"Берегите себя,","пока ждёте.","Сон. Еда. Близкий человек рядом.")

# 5 — first conversation
page("listen","ДИАЛОГ","""
.b{position:absolute;padding:34px 44px;font:500 52px/1.3 Manrope;border-radius:38px}
.b1{right:72px;top:176px;width:690px;background:linear-gradient(160deg,#4d6b38,#34441f);color:#f6efd8;border-bottom-right-radius:8px;box-shadow:0 24px 50px rgba(52,68,31,.35)}
.b2{left:72px;top:420px;width:760px;background:var(--card);border:3px solid var(--ink);border-bottom-left-radius:8px;box-shadow:0 24px 50px rgba(70,56,20,.18)}
.b2 b{font-weight:800}
.dots{position:absolute;left:72px;top:716px;display:flex;gap:12px;padding:20px 28px;border-radius:30px;border-bottom-left-radius:8px;background:rgba(17,21,15,.07)}
.dots i{width:14px;height:14px;border-radius:50%;background:var(--ink);opacity:.5}.dots i:nth-child(2){opacity:.75}.dots i:nth-child(3){opacity:1}
""",
'<div class="b b1">Мы не знаем, с чего начать…</div><div class="b b2"><b>Расскажите, что известно.</b> Остальное — вместе.</div><div class="dots"><i></i><i></i><i></i></div>',
"Сначала —","выслушаем.","Расскажите то, что знаете.")
print("ok")
