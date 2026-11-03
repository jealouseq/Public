'use strict';
/* single source of truth for the film timeline (seconds). Used by film.html (picture) and build_audio2.py (sound). */
(function(root){
function rng(seed){let s=seed>>>0;return()=>((s=(Math.imul(s,1664525)+1013904223)>>>0)/4294967296)}
function compile(start,ops,seed){
  const r=rng(seed);let t=start,str='';const states=[[start-.001,'']],keys=[];let sentAt=null;
  for(const op of ops){
    if(op[0]==='type'){for(const ch of op[1]){t+=(.062+r()*.05)*(ch===' '?1.2:1)+(r()<.08?.13:0);str+=ch;states.push([t,str]);keys.push({t,k:'type'});if('.,—?!'.includes(ch))t+=.17}}
    else if(op[0]==='wait')t+=op[1];
    else if(op[0]==='erase'){const n=str.length;keys.push({t:t+.01,k:'erase0'});for(let i=0;i<n;i++){t+=.05+r()*.014;str=str.slice(0,-1);states.push([t,str]);keys.push({t,k:'erase'})}}
    else if(op[0]==='send'){sentAt=t;keys.push({t,k:'send'})}
  }
  return {start,states,sentAt,keys,end:t};
}
const S=[0,6.9,14.6,24.6,30.2,36.2,40.8,45.8];       // shot starts (chair, tea, phone, child, landline, writing, sunset, end-bg)
const END_T=S[7], T_END=END_T+4.2;
const SHOTS=[
 {img:'p_chair_tulle',oy:78,cam:{s:[1.02,1.09],x:[-.012,.014],y:[.004,-.006],f:[.45,.55],hh:.8},g:{b:.58,c:1.08},scrim:.86},
 {img:'p_tea_window',oy:52,fin:.9,cam:{s:[1.18,1.22],x:[.02,-.008],y:[-.08,-.085],f:[.5,.5],hh:.35},g:{b:.95,c:1.04},scrim:.74},
 {img:'p_phone_glow',oy:42,fin:.9,cam:{s:[1.17,1.21],x:[0,0],y:[-.078,-.083],f:[.5,.5],hh:.25},g:{b:.98,c:1.04},scrim:.72},
 {img:'p_hands_child',oy:44,fin:1.1,cam:{s:[1.03,1.10],x:[.01,-.012],y:[0,-.014],f:[.5,.55],hh:.6},g:{b:.92,c:1.04},scrim:.74},
 {img:'p_rotary_dark',oy:50,fin:1.2,cam:{s:[1.16,1.07],x:[-.012,.006],y:[-.05,-.07],f:[.58,.6],hh:.25},g:{b:1.75,c:1.0},scrim:.7},
 {img:'p_writing_hand',oy:50,fin:.9,cam:{s:[1.04,1.16],x:[.012,-.02],y:[-.006,.008],f:[.34,.52],hh:.7},g:{b:.72,c:1.06},scrim:.92},
 {img:'p_sunrise_window',oy:40,fin:1.0,cam:{s:[1.12,1.2],x:[.008,-.008],y:[-.06,-.075],f:[.5,.5],hh:.25},g:{b:.95,c:1.04},scrim:.82},
 {img:'p_chair_tulle',oy:78,fin:0,blur:30,cam:{s:[1.18,1.22],x:[0,0],y:[0,0],f:[.5,.5],hh:0},g:{b:.34,c:1.0},scrim:.45},
];
SHOTS.forEach((s,i)=>{s.t0=S[i];s.t1=(i<SHOTS.length-1?S[i+1]:T_END)});
// ---- drafts: what a waiting person types (Prata, left aligned). rows: [{text/ops}], top = fraction of frame height
const chair=compile(2.3,[['type','Ты как?'],['wait',.75],['send']],1);
const tea1=compile(S[1]+1.0,[['type','Свет на кухне не гашу.']],2);
const tea2=compile(tea1.end+.75,[['type','Чай завариваю на двоих.']],3);
const ph=compile(S[2]+1.2,[['type','Мне страшно'],['wait',1.0],['erase'],['wait',1.05],['type','Всё хорошо. Мы ждём.'],['wait',.8],['send']],4);
const ch=compile(S[3]+.9,[['type','Дома тебя ждут.'],['wait',.9],['send']],5);
const DRAFTS=[
 {shot:0,top:.67,hide:S[1]-.2,rows:[chair]},
 {shot:1,top:.745,hide:S[2]-.2,rows:[tea1,tea2]},
 {shot:2,top:.80,hide:S[3]-.2,rows:[ph]},
 {shot:3,top:.68,hide:S[4]-.1,rows:[ch]},
];
// ---- set phrases (not typed: "our" voice)
const TEXTS=[
 {cls:'pr',size:86,lines:['Самое трудное —','неизвестность.'],a:S[4]+.9,b:S[4]+4.0,top:.745},
 {cls:'ub',size:58,lines:['Мы —','на связи.'],a:S[4]+4.4,b:S[5]-.3,top:.745,sub:'Центр поисковых решений',subA:S[4]+5.5},
 {cls:'pr',size:76,lines:['Выслушаем.','Запишем всё.','Подскажем, что дальше.'],per:.7,a:S[5]+.6,b:S[6]-.3,top:.60},
 {cls:'ub',size:96,lines:['Мы ищем','ответ.'],a:S[6]+1.6,b:END_T-.6,top:.725},
];
const KEYS=[].concat(...DRAFTS.map(d=>[].concat(...d.rows.map(r=>r.keys))));
const TL={S,END_T,T_END,SHOTS,DRAFTS,TEXTS,KEYS,turn:S[4],phoneSend:ph.sentAt,eraseStart:ph.keys.find(k=>k.k==='erase0').t,
  chairSend:chair.sentAt,childSend:ch.sentAt};
if(typeof module!=='undefined')module.exports=TL;else root.TL=TL;
})(typeof window!=='undefined'?window:globalThis);
