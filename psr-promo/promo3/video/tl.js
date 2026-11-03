'use strict';
/* single source of truth for the film timeline (seconds). Used by film.html (picture) and build_audio.py (sound). */
(function(root){
function rng(seed){let s=seed>>>0;return()=>((s=(Math.imul(s,1664525)+1013904223)>>>0)/4294967296)}
function compile(start,ops,seed){
  const r=rng(seed);let t=start,str='';const states=[[start-.001,'']],keys=[];let sentAt=null;
  for(const op of ops){
    if(op[0]==='type'){for(const ch of op[1]){t+=(.062+r()*.05)*(ch===' '?1.2:1)+(r()<.08?.13:0);str+=ch;states.push([t,str]);keys.push({t,k:'type'});if('.,—?!'.includes(ch))t+=.17}}
    else if(op[0]==='wait')t+=op[1];
    else if(op[0]==='erase'){const n=str.length;keys.push({t:t+.01,k:'erase0'});for(let i=0;i<n;i++){t+=.066+r()*.008;str=str.slice(0,-1);states.push([t,str]);keys.push({t,k:'erase'})}}
    else if(op[0]==='send'){sentAt=t;keys.push({t,k:'send'})}
  }
  return {start,states,sentAt,keys,end:t};
}
// ---- Act 1: one shot on the phone. Messages are typed in the input field and fly up as bubbles.
const msg1=compile(.6,[['type','Ты как?'],['wait',.6],['send']],11);
const msg2=compile(msg1.end+1.25,[['type','Напиши, как сможешь'],['wait',.55],['send']],12);
const msg3=compile(msg2.end+1.4,[['type','Мне страшно'],['wait',.9],['erase'],['wait',.85],['type','Всё хорошо. Мы ждём.'],['wait',.85],['send']],13);
const MSGS=[msg1,msg2,msg3];
const TIMES=['21:07','07:52','23:58'];
const CHIPS=[{t:-.6,text:'3-й день'},{t:msg2.start-.95,text:'10-й день'},{t:msg3.start-.95,text:'21-й день'}];
const P=msg3.sentAt+1.8;
// shot starts: phone, child, rotary, writing, sunrise, end-card background
const S=[0,P,P+5.4,P+12.2,P+17.2,P+22.6];
const END_T=S[5], T_END=END_T+7.8;
const SHOTS=[
 {img:'p_phone_glow',oy:42,cam:{s:[2.5,3.3],x:[0,0],y:[0,0],f:[.5,.5],hh:.12,focus:{ui:[[.5,.52],[.52,.665]],t0:.5,t1:S[1]-3.6},roll:[-.6,.5]},g:{b:.9,c:1.05,sat:.8},scrim:0,ui:true,noGlow:true,outFx:'push',head:true},
 {img:'p_hands_child',oy:44,fin:.85,inFx:'push',outFx:'none',cam:{s:[1.14,1.02],x:[.012,-.012],y:[.004,-.014],f:[.5,.55],hh:.6},g:{b:.9,c:1.04,sat:.9},scrim:.92},
 {img:'p_rotary_dark',oy:50,fin:.9,inFx:'none',outFx:'blur',cam:{s:[1.18,1.08],x:[-.012,.006],y:[-.05,-.07],f:[.58,.6],hh:.25},g:{b:1.55,c:1.0,sat:.7},scrim:.72},
 {img:'p_writing_hand',oy:50,fin:.8,inFx:'rack',outFx:'blur',cam:{s:[1.04,1.16],x:[.012,-.02],y:[-.006,.008],f:[.34,.52],hh:.7},g:{b:.72,c:1.06,sat:.9},scrim:.92},
 {img:'p_sunrise_window',oy:36,fin:1.0,inFx:'rack',outFx:'none',cam:{s:[1.1,1.26],x:[.008,-.008],y:[-.07,-.09],f:[.5,.5],hh:.25},g:{b:.8,c:1.0,sat:.58,hue:-22},scrim:.86,scrimMid:.75,bloomUp:true},
 {img:'p_sunrise_window',oy:36,fin:0,blur:30,cam:{s:[1.2,1.24],x:[0,0],y:[0,0],f:[.5,.5],hh:0},g:{b:.3,c:1.0,sat:.7,hue:-18},scrim:.45},
];
SHOTS.forEach((s,i)=>{s.t0=S[i];s.t1=(i<SHOTS.length-1?S[i+1]:T_END)});
// ---- Act 2: one typed draft over the child's hands (Prata, left aligned)
const ch=compile(S[1]+1.0,[['type','Дома тебя ждут.'],['wait',.9],['send']],5);
const DRAFTS=[{shot:1,top:.745,hide:S[2]-.5,rows:[ch]}];
// ---- set phrases ("our" voice), each with its own way of appearing
const TEXTS=[
 {cls:'pr',size:84,lines:['Самое трудное —','неизвестность.'],a:S[2]+.9,b:S[2]+3.3,top:.745,anim:'words'},
 {cls:'pr',size:84,lines:['Мы —','на связи.'],per:.22,a:S[2]+3.7,b:S[3]-.5,top:.70,sub:'Центр поисковых решений',subA:S[2]+4.5,anim:'fade'},
 {cls:'pr',size:74,lines:['Выслушаем.','Запишем всё.','Подскажем, что дальше.'],per:.75,a:S[3]+.8,b:S[4]-.6,top:.60,anim:'fade'},
 {cls:'pr',size:112,lines:['Мы ищем','ответ.'],a:S[4]+1.5,b:END_T-.7,top:.66,anim:'sweep'},
];
const KEYS=[].concat(...MSGS.map(m=>m.keys),...DRAFTS.map(d=>[].concat(...d.rows.map(r=>r.keys))));
const LEAKS=[{t:S[2],a:.4},{t:S[4],a:.7,d:.45}];
const TL={LEAKS,S,P,END_T,T_END,SHOTS,DRAFTS,TEXTS,KEYS,MSGS,CHIPS,TIMES,turn:S[2],phoneSend:msg3.sentAt,eraseStart:msg3.keys.find(k=>k.k==='erase0').t,childSend:ch.sentAt};
if(typeof module!=='undefined')module.exports=TL;else root.TL=TL;
})(typeof window!=='undefined'?window:globalThis);
