#!/usr/bin/env python3
"""Storyboard board for the new-style (paper / ink / marker) promo. python3 -I tools/make_storyboard.py -> src/storyboard.html"""
import os
ROOT=os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TICK='<svg width="34" height="25" viewBox="0 0 34 25" fill="none" stroke="#f2c46f" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13l8 8L30 4"/></svg>'
def bub(txt,top,right=72,size=84,scale=1,op=1,tick=True,time="23:58"):
    return f'<div class="b" style="top:{top}px;right:{right}px;font-size:{size}px;transform:scale({scale});opacity:{op}">{txt}<div class="m">{time} {TICK if tick else ""}</div></div>'
def pill(txt,top,scale=1,op=1):
    return f'<div class="pl" style="top:{top}px;transform:translateX(-50%) scale({scale});opacity:{op}">{txt}</div>'
def stamp(cx,cy,r,col,txt,num,rot=0,k=1):
    return f'''<g transform="translate({cx} {cy}) rotate({rot}) scale({k})" fill="none" stroke="{col}"><circle r="{r}" stroke-width="9"/><circle r="{r-18}" stroke-width="3"/><circle r="{r*.45}" stroke-width="3"/><text font-family="Oswald" font-weight="600" font-size="{r*.15}" letter-spacing="3" fill="{col}" stroke="none" text-anchor="middle"><textPath href="#cp" startOffset="50%">{txt}</textPath></text><text y="{r*.2}" text-anchor="middle" font-family="Cormorant Garamond" font-weight="700" font-size="{r*.74}" fill="{col}" stroke="none">{num}</text><text y="{r*.78}" text-anchor="middle" font-family="Oswald" font-size="{r*.15}" letter-spacing="7" fill="{col}" stroke="none">★ ★ ★</text></g>'''
def marker_loop(cx,cy,rx,ry,seed=3):
    import math,random
    random.seed(seed); d=""
    N=40
    for k in range(N+5):
        t=k/N*math.pi*2+math.pi*1.15; j=1+random.uniform(-.014,.014); g=1+.05*(k/N)
        x=cx+math.cos(t)*rx*j*g; y=cy+math.sin(t)*ry*j*g
        d+=("L" if k else "M")+f"{x:.1f} {y:.1f}"
    return f'<path d="{d}" fill="none" stroke="#a8412b" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>'
WIN='''<svg style="position:absolute;left:300px;top:150px" width="480" height="800" viewBox="-12 -12 460 764"><defs><clipPath id="ar"><path d="M0 740V218A218 218 0 0 1 436 218V740Z"/></clipPath><linearGradient id="rm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff0c2"/><stop offset=".55" stop-color="#f6c872"/><stop offset="1" stop-color="#d98f3d"/></linearGradient><radialGradient id="lp" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fffbe6" stop-opacity=".95"/><stop offset="1" stop-color="#fff0c2" stop-opacity="0"/></radialGradient></defs><g clip-path="url(#ar)"><rect width="436" height="740" fill="url(#rm)"/><circle cx="322" cy="330" r="190" fill="url(#lp)"/><g fill="#b9772c" opacity=".55"><rect x="40" y="250" width="120" height="10"/><rect x="44" y="196" width="16" height="54"/><rect x="64" y="208" width="14" height="42"/><rect x="82" y="190" width="18" height="60"/><rect x="104" y="204" width="14" height="46"/></g><path d="M278 360l22-62h56l22 62z" fill="#fff8de"/><path d="M328 360v200" stroke="#7a4f1c" stroke-width="9"/><ellipse cx="328" cy="566" rx="40" ry="9" fill="#7a4f1c"/><rect x="0" y="548" width="436" height="192" fill="#8a5a1e"/><rect x="0" y="548" width="436" height="14" fill="#a8701f"/><path d="M82 556h82l-10 64H92z" fill="#fff6dc"/><path d="M164 570c26 0 26 34 0 34" fill="none" stroke="#fff6dc" stroke-width="10"/><path d="M104 536c-18-24 18-30 0-60M134 536c-18-24 18-30 0-60" fill="none" stroke="rgba(255,255,255,.75)" stroke-width="6" stroke-linecap="round"/><path d="M218 0v740M0 330h436" stroke="#1d2615" stroke-width="14"/></g><path d="M0 740V218A218 218 0 0 1 436 218V740Z" fill="none" stroke="#1d2615" stroke-width="22"/><rect x="-14" y="716" width="464" height="24" fill="#1d2615"/></svg>'''
def bars(n,x0,y,h,col,seed=2,w=12,gap=17):
    import random; random.seed(seed); import math
    s=""
    for i in range(n):
        a=24+abs(math.sin(i*.45))*h*(.4+random.random()*.6)
        s+=f'<rect x="{x0+i*gap}" y="{y-a/2:.0f}" width="{w}" height="{a:.0f}" rx="{w/2}" fill="{col}"/>'
    return s
def scrib(x,y,w,seed):
    import random,math; random.seed(seed); d=f"M{x} {y}"; cx=x
    while cx<x+w:
        cx+=random.uniform(10,26); d+=f"Q{cx-8:.0f} {y+random.uniform(-22,22):.0f} {cx:.0f} {y+random.uniform(-6,6):.0f}"
    return f'<path d="{d}" fill="none" stroke="#1d2a3a" stroke-width="5" stroke-linecap="round" opacity=".85"/>'

F=[]
# 01
F.append(dict(t="0:00–0:03",name="Тишина",
 fr=pill("3-й день",340)+bub("Ты как?",460,size=104,time="09:41"),
 desc="Чистый лист бумаги. Тишина, лёгкий шорох. Въезжает одно сообщение: «Ты как?». Рядом одна галочка.",
 mot="Пузырь выезжает справа с пружиной; камера медленно наезжает 100→108 %.",
 txt="«Ты как?»", snd="тихий шорох бумаги, мягкий «пуф» пузыря, далёкие часы"))
# 02
F.append(dict(t="0:03–0:07",name="Идут дни",
 fr=pill("3-й день",150,.8,.45)+bub("Ты как?",205,size=70,scale=.6,op=.45,time="09:41")+pill("10-й день",420,.9,.7)+bub("Напиши, как сможешь",475,size=70,scale=.9,op=.75,time="21:15")+pill("21-й день",760)+bub("Мы ждём тебя",815,size=96,time="23:58"),
 desc="Лента чата прокручивается вверх, старые сообщения бледнеют. Дни меняются: 3-й, 10-й, 21-й. Ответа нет.",
 mot="Ритмичные «тики» часов; каждое новое сообщение выпрыгивает, старые уходят вглубь.",
 txt="«Напиши, как сможешь» → «Мы ждём тебя»", snd="тики часов (по битам), короткие «поп» на каждое сообщение"))
# 03
tick='<svg style="position:absolute;left:100px;top:130px" width="880" height="620" viewBox="0 0 880 620" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M520 340 L690 510 L860 140" stroke="#a9a58a" stroke-width="30" stroke-dasharray="1 52" transform="translate(40 0)"/><path d="M120 360 L290 530 L560 160" stroke="#11150f" stroke-width="64"/>'+marker_loop(340,350,300,230)+'</svg>'
F.append(dict(t="0:07–0:10",name="Одна галочка",
 fr=tick+'<div class="hd" style="top:840px">Когда в чате —<br><u>одна галочка</u></div>',
 desc="Крупный план галочки. Красный маркер обводит её, рядом точками дорисовывается вторая, которой нет. Заголовок падает снизу.",
 mot="Маркер рисуется «от руки» за 0,8 с; точки второй галочки появляются по одной.",
 txt="«Когда в чате — одна галочка»", snd="скрип маркера (Mixkit «Pen marker line»), тишина, один низкий тон"))
# 04
F.append(dict(t="0:10–0:13",name="Ответ",
 fr='<svg style="position:absolute;right:70px;top:90px" width="240" height="240" viewBox="-130 -130 260 260">'+stamp(0,0,115,"#34441f","ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ","ЦПР",-12)+'</svg><div class="hd big" style="top:520px">Мы ищем<br><u>ответ.</u></div>',
 desc="Бумага «переворачивается». Крупный заголовок, маркер подчёркивает слово «ответ». Сверху ставится штамп ЦПР.",
 mot="Слова вылетают по одному из-за маски; маркер дорисовывается; штамп «бьёт» сверху.",
 txt="«Мы ищем ответ.»", snd="шорох переворота, глухой удар штампа, чистый тёплый аккорд (вступление музыки)"))
# 05
F.append(dict(t="0:13–0:17",name="Выслушаем",
 fr='<div class="v"><div class="pl2"><svg width="60" height="60" viewBox="0 0 60 60"><path d="M8 4l48 26L8 56z" fill="#34441f"/></svg></div><svg width="520" height="220" viewBox="0 0 520 220">'+bars(34,0,110,180,"#f6eed6")+'</svg><div class="du">2:14</div></div><div class="rp">Мы слушаем.</div><svg style="position:absolute;left:690px;top:610px" width="320" height="230" fill="none" stroke="#34441f" stroke-linecap="round" stroke-width="9"><path d="M60 62a70 70 0 0 1 0 106"/><path d="M110 28a118 118 0 0 1 0 174" opacity=".6"/><path d="M162 -6a166 166 0 0 1 0 242" opacity=".3"/></svg><div class="hd" style="top:960px;font-size:116px">Сначала —<br>выслушаем.</div>',
 desc="Голосовое сообщение: волна «играет» слева направо. Приходит спокойный ответ. От него расходятся дуги, как звук, который принимают.",
 mot="Волна заполняется кремовым цветом по ходу воспроизведения; дуги пульсируют.",
 txt="«Мы слушаем.» / «Сначала — выслушаем»", snd="тихая запись голоса (эффект шёпота), мягкий «дзынь» ответа"))
# 06
form='<div class="card" style="position:absolute;left:110px;top:190px;width:860px;height:700px;transform:rotate(-2deg);padding:48px 56px"><div style="position:absolute;left:50%;top:-20px;width:150px;height:40px;margin-left:-75px;background:rgba(214,196,142,.78);transform:rotate(2deg)"></div><div class="lb">СВЕДЕНИЯ О БОЙЦЕ</div>'
for i,(l,s) in enumerate((("ФИО",3),("ПОЗЫВНОЙ",5),("ЧАСТЬ",8),("ГДЕ И КОГДА БЫЛ ПОСЛЕДНИЙ КОНТАКТ",12))):
    form+=f'<div class="fd"><b>{l}</b><svg width="740" height="60" style="position:absolute;left:10px;top:30px">{scrib(150 if i<3 else 6, 36 if i<3 else 52, 420 if i<3 else 560,s)}</svg></div>'
form+='</div>'
F.append(dict(t="0:17–0:21",name="Начните с того, что знаете",
 fr=form+'<div class="hd" style="top:960px;font-size:112px">Начните с того,<br><u>что знаете.</u></div>',
 desc="Анкета с пустыми строками ложится на стол. Рукописные строчки заполняются синей ручкой (нечитаемый «почерк», без реальных данных).",
 mot="Анкета падает с лёгким отскоком; линии пера прорисовываются построчно.",
 txt="«Начните с того, что знаете.»", snd="шелест бумаги, письмо ручкой (Mixkit «Writing scribble on paper»)"))
# 07
F.append(dict(t="0:21–0:24",name="Официальный путь",
 fr='<div class="hd" style="top:110px;font-size:120px">Официальный<br><u>путь.</u></div><svg style="position:absolute;left:0;top:470px" width="1080" height="800" viewBox="0 0 1080 800">'+stamp(220,200,150,"#34441f","КОМАНДОВАНИЕ ЧАСТИ","1",-8)+stamp(560,380,150,"#34441f","ВОЕННЫЙ КОМИССАРИАТ","2",5)+stamp(880,600,170,"#a8412b","КОПИИ — СЕБЕ","3",-4,1.06)+'<g stroke="#a8412b" stroke-width="7" stroke-linecap="round"><path d="M800 410l-30-50M880 395v-60M960 410l30-50"/></g></svg>',
 desc="Три штампа последовательно «бьют» по бумаге: часть → военкомат → копии себе. Пунктир между ними прочерчивается стрелкой.",
 mot="Штамп с увеличением 120→100 % и всплеском туши; бумага чуть вздрагивает на каждом ударе.",
 txt="«Официальный путь»", snd="три глухих удара штампа (низкий «тум» + шорох), пунктир — тихие тики"))
# 08
F.append(dict(t="0:24–0:27",name="Рядом",
 fr='<div style="position:absolute;inset:0;background:radial-gradient(closest-side at 50% 42%,rgba(242,196,111,.78),rgba(242,196,111,.25) 60%,transparent)"></div>'+WIN+'<div class="hd" style="top:1010px;font-size:170px;text-align:center;left:0;right:0">Вы <u>не одни.</u></div>',
 desc="Кадр теплеет: бумага наливается золотым светом. В арочном окне загорается лампа, парит чашка. Камера мягко въезжает в окно.",
 mot="Свет «включается» за 0,6 с, пар медленно поднимается; лёгкий наезд.",
 txt="«Вы не одни.»", snd="тёплый нарастающий аккорд, мягкий щелчок лампы"))
# 09
F.append(dict(t="0:27–0:30",name="Финал",
 fr='<svg style="position:absolute;left:190px;top:170px" width="700" height="700" viewBox="-350 -350 700 700"><defs><path id="cp2" d="M0 0m-250 0a250 250 0 1 1 500 0a250 250 0 1 1 -500 0"/></defs><g transform="rotate(-8)" fill="none" stroke="#34441f"><circle r="330" stroke-width="16"/><circle r="296" stroke-width="5"/><circle r="170" stroke-width="5"/><text font-family="Oswald" font-weight="600" font-size="46" letter-spacing="9" fill="#34441f" stroke="none" text-anchor="middle"><textPath href="#cp2" startOffset="25%">ЦЕНТР ПОИСКОВЫХ РЕШЕНИЙ</textPath></text><text y="60" text-anchor="middle" font-family="Cormorant Garamond" font-weight="700" font-size="190" fill="#34441f" stroke="none">ЦПР</text></g></svg><div class="hd" style="top:920px;font-size:84px;text-align:center;left:0;right:0">Профессиональный розыск<br>в зоне СВО</div><div class="ct">Контакты — <u>в описании канала</u></div>',
 desc="Бумага замирает. Большой штамп ЦПР в центре, ниже название и одна строка про контакты с красным маркером.",
 mot="Штамп бьёт в центр; текст проявляется; последние 0,5 с статичный кадр.",
 txt="«Центр поисковых решений. Профессиональный розыск в зоне СВО. Контакты — в описании канала»", snd="финальный удар штампа, музыка затихает на длинном тоне"))

CSS='''
:root{--ink:#11150f;--paper:#ebe2ca;--olive:#34441f;--rust:#a8412b;--card:#f7f1de}
*{box-sizing:border-box;margin:0;padding:0}html,body{background:#d9d0b8}
.board{width:1800px;padding:60px;background:#e4dbc3;font-family:Manrope,sans-serif;color:var(--ink)}
.hdr{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid var(--ink);padding-bottom:28px}
.hdr h1{font:600 100px/1 'Cormorant Garamond',serif;letter-spacing:-.03em}.hdr h1 u{text-decoration:none;background:linear-gradient(transparent 70%,rgba(168,65,43,.9) 70% 88%,transparent 88%)}
.hdr p{font:500 26px/1.45 Manrope;color:#2a3020;max-width:720px;margin-top:14px}
.spec{font:600 20px/1.8 Oswald;letter-spacing:.14em;text-align:right;color:var(--olive)}
.spec b{color:var(--rust)}
.strip{display:flex;gap:30px;margin:34px 0 40px;align-items:stretch}
.sw{display:flex;gap:10px;align-items:center}.sw i{display:block;width:70px;height:70px;border-radius:6px;box-shadow:inset 0 0 0 2px rgba(0,0,0,.12)}
.as{display:flex;gap:14px}.as div{width:150px}.as img{width:150px;height:96px;object-fit:cover;border-radius:5px;display:block;box-shadow:0 4px 10px rgba(0,0,0,.2)}.as small{display:block;font:500 15px/1.3 Manrope;margin-top:6px;color:#3a402c}
.fonts{font:600 22px/1.5 Manrope}.fonts em{font:italic 600 34px 'Cormorant Garamond',serif}.fonts span{font-family:Oswald;letter-spacing:.12em}.fonts s{text-decoration:none;font:600 30px Caveat}
.grid{display:grid;grid-template-columns:repeat(3,540px);gap:56px 30px}
.cell{width:540px}
.fw{position:relative;width:540px;height:675px;overflow:hidden;border-radius:6px;box-shadow:0 14px 30px rgba(50,40,10,.28)}
.num{position:absolute;left:12px;top:12px;z-index:9;width:40px;height:40px;border-radius:50%;background:var(--rust);color:#f8efd6;font:700 22px Oswald;display:grid;place-items:center}
.fr{position:absolute;left:0;top:0;width:1080px;height:1350px;transform:scale(.5);transform-origin:0 0;background:#ebe2ca url(/assets/sb/paper_watercolor.jpg) center/cover;background-blend-mode:multiply;overflow:hidden}
.tm{font:600 22px Oswald;letter-spacing:.2em;color:var(--rust);margin:16px 0 4px}
.cell h3{font:600 40px/1 'Cormorant Garamond',serif;letter-spacing:-.02em;margin-bottom:10px}
.cell p{font:500 17px/1.45 Manrope;color:#2a3020;margin-bottom:6px}.cell p b{font:700 14px Oswald;letter-spacing:.18em;color:var(--olive);margin-right:8px}
.ft{margin-top:46px;border-top:3px solid var(--ink);padding-top:26px;display:grid;grid-template-columns:1fr 1fr;gap:40px;font:500 20px/1.55 Manrope}
.ft h4{font:600 24px Oswald;letter-spacing:.18em;color:var(--olive);margin-bottom:8px}
/* in-frame parts */
.b{position:absolute;padding:30px 48px 26px;border-radius:52px 52px 12px 52px;background:linear-gradient(160deg,#5b7e44,#34441f);color:#f6efd8;font:500 84px/1.1 Manrope;letter-spacing:-.02em;box-shadow:0 30px 60px rgba(52,68,31,.35);transform-origin:100% 50%}
.b .m{display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:6px;font:500 .32em Manrope;color:rgba(255,240,205,.88)}
.pl{position:absolute;left:50%;padding:12px 32px;border-radius:40px;background:rgba(17,21,15,.1);font:600 32px Manrope;letter-spacing:.04em;color:#3a402c;white-space:nowrap}
.hd{position:absolute;left:72px;font:600 134px/.98 'Cormorant Garamond',serif;letter-spacing:-.035em;color:var(--ink)}
.hd.big{font-size:236px}
.hd u,.ct u{text-decoration:none;background:linear-gradient(transparent 76%,rgba(168,65,43,.9) 76% 92%,transparent 92%);box-decoration-break:clone}
.ct{position:absolute;left:0;right:0;top:1150px;text-align:center;font:600 50px Oswald;letter-spacing:.14em;color:var(--olive)}
.card{background:var(--card);box-shadow:0 26px 60px rgba(70,56,20,.24),0 3px 8px rgba(70,56,20,.16)}
.lb{font:600 28px Oswald;letter-spacing:.26em;color:var(--olive);margin-bottom:12px}
.fd{position:relative;height:130px;border-bottom:3px solid rgba(17,21,15,.7)}.fd b{position:absolute;left:0;top:16px;font:600 30px Oswald;letter-spacing:.2em;color:#3b4430}
.v{position:absolute;left:72px;right:72px;top:150px;height:300px;border-radius:56px 56px 12px 56px;background:linear-gradient(160deg,#5b7e44,#34441f);box-shadow:0 30px 60px rgba(52,68,31,.38);display:flex;align-items:center;gap:30px;padding:0 50px}
.pl2{flex:none;width:140px;height:140px;border-radius:50%;background:#f6eed6;display:grid;place-items:center}.pl2 svg{margin-left:8px}
.du{font:600 44px Oswald;color:#f6eed6;letter-spacing:.08em}
.rp{position:absolute;left:72px;top:520px;padding:34px 58px;border-radius:50px 50px 50px 12px;background:var(--card);border:3.5px solid var(--ink);font:800 80px/1.05 Manrope;letter-spacing:-.025em;box-shadow:0 28px 56px rgba(70,56,20,.2)}
'''
cells=""
for i,f in enumerate(F,1):
    cells+=f'''<div class="cell"><div class="fw"><div class="num">{i}</div><div class="fr">{f["fr"]}</div></div>
<div class="tm">{i:02d} · {f["t"]}</div><h3>{f["name"]}</h3>
<p><b>КАДР</b>{f["desc"]}</p><p><b>ДВИЖЕНИЕ</b>{f["mot"]}</p><p><b>ТЕКСТ</b>{f["txt"]}</p><p><b>ЗВУК</b>{f["snd"]}</p></div>'''
html=f'''<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>ЦПР — сторибоард ролика</title>
<link rel="stylesheet" href="/fonts/fonts.css"><link rel="stylesheet" href="/fonts/post.css"><link rel="stylesheet" href="/fonts/post2.css"><link rel="stylesheet" href="/fonts/sb.css"><style>{CSS}</style></head><body>
<svg width="0" height="0" style="position:absolute"><defs><path id="cp" d="M0 0m-100 0a100 100 0 1 1 200 0"/></defs></svg>
<div class="board" id="board">
<div class="hdr"><div><h1>Сторибоард <u>ролика</u></h1><p>Центр поисковых решений · рекламный ролик в новом стиле: тёплая бумага, чёрная тушь, красный маркер, резиновые штампы. Сюжет про ожидание: от одной галочки в чате до «Мы ищем ответ».</p></div>
<div class="spec"><b>30 СЕК</b> · 30 КАДР/С<br>1080×1350 (4:5) + 1080×1920 (9:16)<br>ТЕКСТ НА ЭКРАНЕ · БЕЗ ДИКТОРА<br>МУЗЫКА + SFX · ВАРИАНТ БЕЗ ЗВУКА</div></div>
<div class="strip">
 <div><div class="sw"><i style="background:#ebe2ca"></i><i style="background:#11150f"></i><i style="background:#34441f"></i><i style="background:#a8412b"></i><i style="background:#f2c46f"></i></div><small style="font:500 15px Manrope;color:#3a402c">бумага · тушь · олива · маркер · тёплый свет</small></div>
 <div class="as"><div><img src="/assets/sb/paper_watercolor.jpg"><small>ambientCG Paper001 (CC0) — основа кадра</small></div><div><img src="/assets/sb/paper_crumpled.jpg"><small>ambientCG Paper003 (CC0) — переходы-«смятие»</small></div></div>
 <div class="fonts"><em>Cormorant Garamond</em> · <span>OSWALD</span> · Manrope · <s>Caveat (рукопись)</s><br><small style="font:500 15px Manrope;color:#3a402c">шрифты Google Fonts (SIL OFL); звук: Mixkit License</small></div>
</div>
<div class="grid">{cells}</div>
<div class="ft"><div><h4>МУЗЫКА И ЗВУК</h4>Тихая фортепианная подложка из библиотеки Mixkit (~70 bpm), выберем на прослушивании из каталога проекта. Эффекты: «Pen marker line», «Writing scribble on paper», «Signing on a paper», «Papers being moved or wrinkling» (Mixkit), глухой удар штампа собираем из низкого «тума» и шороха бумаги. Музыка не заглушает смысл: ключевые удары на 10-й и 21-й секундах.</div>
<div><h4>ПРАВИЛА РОЛИКА</h4>Без крови и боевых кадров, без обещаний результата и без упоминания оплаты. Каждый кадр держится минимум 2,5 секунды, чтобы текст успевали прочитать. Весь текст читается и без звука. Контакты только «в описании канала».</div></div>
</div></body></html>'''
open(f"{ROOT}/src/storyboard.html","w").write(html); print("ok")
