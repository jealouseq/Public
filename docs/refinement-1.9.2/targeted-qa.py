"""Read-only final packaged1.9.2 acceptance. Never forwards writes; two armed fixturePOSTs only."""
from pathlib import Path
from types import SimpleNamespace
from urllib.parse import urlparse
import argparse,importlib.util,json,hashlib,struct,re,traceback
from playwright.sync_api import sync_playwright,expect
ROOT=Path('/workspace/Public');OUT=ROOT/'work/refinement-1.9.2/final';SCREENS=OUT/'screens'
parser=argparse.ArgumentParser();parser.add_argument('--build-ready',action='store_true',required=True);parser.add_argument('--expect-main',required=True);parser.add_argument('--expect-css',required=True);args=parser.parse_args()
entry=json.loads((ROOT/'wordpress/joyrent/assets/dist/.vite/manifest.json').read_text())['src/main.tsx'];assert Path(entry['file']).name==args.expect_main and Path(entry['css'][0]).name==args.expect_css
spec=importlib.util.spec_from_file_location('qa',ROOT/'work/refinement-1.7/browser-qa.py');qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.ARGS=SimpleNamespace(expect_main=args.expect_main,expect_css=args.expect_css);qa.SCREENS=SCREENS
REQUESTED='QA Unlisted Game';NAME='QA Test';PHONE='+380500000000';records=[];shorts=[];native=[];screens=[];boot={}
def check(c,m):qa.check(c,m)
def norm(t):return re.sub(r'\s+',' ',t).strip()
def frame(p):p.evaluate('()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))')
def target44(loc):b=qa.box(loc);check(b['w']>=43.99 and b['h']>=43.99,f'Target below44px:{b}')
def no_old_copy(p):
 text=p.evaluate("""()=>{const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT),a=[];while(walker.nextNode()){let n=walker.currentNode;if(!n.parentElement?.closest('script,style,noscript'))a.push(n.textContent)}return a.join(' ')+' '+[...document.querySelectorAll('[aria-label],[title],[placeholder],[alt]')].flatMap(e=>['aria-label','title','placeholder','alt'].map(n=>e.getAttribute(n)||'')).join(' ')}""")
 matches=re.findall(r'.{0,35}заяв[\wа-яіїєґё]*.{0,35}',text,re.I);check(not matches,f'Old visitor booking copy:{matches}')
 return {'oldApplicationWordMatches':0,'hasBookingTerminology':bool(re.search(r'брон',text,re.I))}
def focus_trap(p,selector):
 for key in ['Tab','Shift+Tab']:
  for _ in range(6):p.keyboard.press(key);check(p.evaluate('(s)=>!document.hasFocus()||!!document.activeElement?.closest(s)',selector),'Modal focus escaped')
def capture(s,phase,selector=None,allcases=False):
 if not allcases and (s.lang,s.width) not in [('uk',390),('ru',320),('uk',1440)]:return
 if selector:qa.scroll(s.page,selector,100 if phase=='consent' else 24)
 s.page.evaluate('document.activeElement?.blur()');qa.settle(s.page)
 path=SCREENS/f'{s.lang}-{s.width}-{phase}.png';s.page.screenshot(path=str(path),full_page=False);raw=path.read_bytes();w,h=struct.unpack('>II',raw[16:24])
 screens.append({'file':str(path.relative_to(ROOT)),'phase':phase,'language':s.lang,'width':w,'height':h,'sha256':hashlib.sha256(raw).hexdigest(),'accepted':False})
def capture_native(p,lang,phase):
 p.evaluate('scrollTo({top:0,behavior:"instant"});document.activeElement?.blur()');qa.settle(p)
 path=SCREENS/f'{lang}-1440-native-{phase}.png';p.screenshot(path=str(path),full_page=False);raw=path.read_bytes();screens.append({'file':str(path.relative_to(ROOT)),'phase':'native-'+phase,'language':lang,'width':1440,'height':1000,'sha256':hashlib.sha256(raw).hexdigest(),'accepted':False})
class TouchBrowser:
 def __init__(self,b):self.browser=b
 def new_context(self,**kw):kw['has_touch']=kw['viewport']['width']<=700;return self.browser.new_context(**kw)
class Session(qa.Session):
 def __init__(self,*a,**kw):super().__init__(*a,**kw);self.armed=False;self.fixtures=[];self.expected={}
 def route(self,route):
  r=route.request;u=urlparse(r.url)
  if self.armed and r.method=='POST' and u.hostname=='localhost' and u.path=='/wp-json/joyrent/v1/requests':
   self.armed=False;p=r.post_data_json;checks={k:p.get(k)==v for k,v in self.expected.items()};checks.update(noCity='city' not in p,requestId=bool(re.fullmatch('[a-f0-9-]{32,40}',p.get('requestId',''))),exactKeys=set(p)==set(self.expected)|{'requestId'})
   self.fixtures.append({'interceptedBeforeTransmit':True,'payloadChecks':checks,'status':'awaiting_confirmation','realOrders':0,'realEmails':0})
   if not all(checks.values()):route.abort('blockedbyclient');return
   route.fulfill(status=200,content_type='application/json',body=json.dumps({'reference':f'QA-FIXTURE-{self.lang}-192','rentalAmount':1400,'status':'awaiting_confirmation'}));return
  super().route(route)
def hero(s):
 p=s.page;qa.hero(s);b=qa.box(p.locator('.hero-primary'));expected=172.859375 if s.lang=='uk' else 181.4375
 check(abs(b['w']-expected)<.05 and b['h']==54,f'CTA differs from desktop sizing:{b}')
 check(p.locator('.hero-primary').evaluate('e=>getComputedStyle(e).fontSize')=='15px','Hero CTA font differs from desktop')
 if s.width<=700:
  check(p.locator('.hero-content').evaluate('e=>getComputedStyle(e).textAlign')=='center','Mobile hero lost centering')
  link=qa.box(p.locator('.hero-actions .text-link'));check(b['bottom']<link['y'],'Mobile CTA and tariffs not stacked')
 qa.scroll(p,'header',0);capture(s,'hero',allcases=True)
 p.locator('.hero-primary').click();check(urlparse(p.url).fragment=='rates','Hero route wrong');expect(p.locator('#rates .console-switch button').filter(has_text='PS5')).to_have_attribute('aria-pressed','true')
 benefits=[norm(t) for t in p.locator('.tariff-benefit').all_inner_texts() if norm(t)];check(benefits==['Доставка включена','Доставка включена'],f'Tariff badge copy differs:{benefits}')
 s.metrics['hero']={'cta':b,'desktopWidthExpected':expected,'font':'15px','route':'#rates/PS5','tariffBadges':benefits};no_old_copy(p)
def booking(s):
 p=s.page;expect(p.locator('[data-intent="continue"]')).to_be_enabled()
 p.locator('.controller-options button').filter(has_text=re.compile('^2$')).click();expect(p.locator('.summary-lines')).to_contain_text('Без доплати' if s.lang=='uk' else 'Без доплаты');expect(p.locator('.summary-price')).to_contain_text('1 400')
 p.locator('.security-choice').filter(has=p.locator('input[value="contract"]')).click();expect(p.locator('.summary-security')).to_be_visible();expect(p.locator('.summary-price')).to_contain_text('1 400')
 p.locator('#booking .console-switch button').filter(has_text='PS4').click();p.locator('.security-choice').filter(has=p.locator('input[value="deposit"]')).click();expect(p.locator('.summary-deposit dd')).to_contain_text('7 500')
 p.locator('#booking .console-switch button').filter(has_text='PS5').click();expect(p.locator('.summary-deposit dd')).to_contain_text('25 000');p.locator('.security-choice').filter(has=p.locator('input[value="contract"]')).click()
 icon=p.locator('.booking-games-icon').evaluate("""e=>{let s=getComputedStyle(e),v=e.querySelector('svg');return{border:s.border,background:s.backgroundColor,color:s.color,ariaHidden:e.getAttribute('aria-hidden'),svgWidth:v.getBoundingClientRect().width,svgHeight:v.getBoundingClientRect().height,viewBox:v.getAttribute('viewBox'),pathStart:v.querySelector('path')?.getAttribute('d')?.slice(0,26),fill:v.getAttribute('fill')}}""")
 check(icon['border'].startswith('0px') and icon['background']=='rgba(0, 0, 0, 0)' and icon['svgWidth']==28 and icon['svgHeight']==28 and icon['ariaHidden']=='true','PS mark tile/art size wrong');check(icon['viewBox']=='0 0 24 24' and icon['fill']=='currentColor' and icon['pathStart'].startswith('M8.984 2.596v17.547'),'Booking games art differs from intendedPS mark')
 capture(s,'booking-games','.booking-games');s.metrics['booking']={'icon':icon,'controllers':2,'security':'contract','rentalAmount':1400,'PS4deposit':7500,'PS5deposit':25000}
def picker(s):
 p=s.page;opener=p.locator('.game-picker-button');opener.click();expect(p.locator('.game-picker-dialog[open]')).to_be_visible();qa.settle(p)
 cards=p.locator('.picker-game').evaluate_all("""es=>es.map(e=>{const r=e.getBoundingClientRect(),a=e.querySelector('.picker-game-toggle').getBoundingClientRect(),h=e.querySelector('h3'),s=getComputedStyle(h);return{title:h.textContent,rowTop:r.top,bottom:r.bottom,actionTop:a.top,actionBottom:a.bottom,titleScroll:h.scrollHeight,titleClient:h.clientHeight,overflow:s.overflow,lineClamp:s.webkitLineClamp,textOverflow:s.textOverflow}})""")
 check(len(cards)>10,'PS5 catalog unexpectedly short')
 rows={}
 for c in cards:
  rows.setdefault(round(c['rowTop'],1),[]).append(c);check(c['titleScroll']<=c['titleClient']+1 and c['lineClamp'] in ['none','0'] and c['textOverflow']!='ellipsis','Title clipped')
 for row in rows.values():check(max(c['actionTop'] for c in row)-min(c['actionTop'] for c in row)<1 and max(c['actionBottom'] for c in row)-min(c['actionBottom'] for c in row)<1,'Add actions are misaligned within a catalog row')
 capture(s,'picker-catalog')
 panel=p.locator('.game-picker-request');panel.scroll_into_view_if_needed();frame(p);expect(panel).to_have_attribute('data-request-open','false')
 heading=qa.box(panel.locator('h3'));action=qa.box(panel.locator('.missing-game-toggle'));pb=qa.box(panel);check(abs(heading['cx']-pb['cx'])<1.5 and abs(action['cx']-pb['cx'])<1.5 and heading['bottom']<action['y'],'Closed missing-game heading/action not centered and stacked')
 capture(s,'missing-closed');no_old_copy(p)
 p.locator('.game-picker-search input').fill(REQUESTED);p.locator('.game-picker-empty-actions button').filter(has_text='Запросити гру' if s.lang=='uk' else 'Запросить игру').click()
 field=p.locator('.missing-game-fields input');expect(field).to_be_focused();expect(field).to_have_value(REQUESTED);check(field.evaluate('e=>getComputedStyle(e).textAlign')=='start' or field.evaluate('e=>getComputedStyle(e).textAlign')=='left','Open request input not left aligned');check(field.evaluate('e=>getComputedStyle(e).fontSize')=='16px','Request input below16px')
 capture(s,'missing-open');no_old_copy(p)
 # screenshot blur does not alter the retained request; closing returns focus to the opener.
 p.locator('.missing-game-toggle').click();expect(field).to_be_hidden();expect(p.locator('.missing-game-saved')).to_contain_text(REQUESTED)
 p.locator('.game-picker-search input').fill('');p.locator('.game-picker-scroll').evaluate('e=>e.scrollTop=0');frame(p)
 first=p.locator('.picker-game-toggle').first;second=p.locator('.picker-game-toggle').nth(1);first.click();second.click();expect(first).to_have_attribute('aria-pressed','true');expect(second).to_have_attribute('aria-pressed','true');second.click();expect(second).to_have_attribute('aria-pressed','false');focus_trap(p,'.game-picker-dialog[open]')
 p.locator('.game-picker-footer .button').click();expect(opener).to_be_focused();expect(p.locator('.game-picker-count')).to_have_text('1');expect(p.locator('.booking-games .selected-game span')).to_have_text(cards[0]['title']);expect(p.locator('.requested-game-note')).to_contain_text(REQUESTED);no_old_copy(p)
 serial=p.evaluate('JSON.stringify({session:{...sessionStorage},local:{...localStorage}})');check(REQUESTED not in serial,'Requested title stored publicly')
 s.metrics['picker']={'cards':cards,'rowsChecked':len(rows),'alignedEveryRow':True,'closedHeadingCentered':True,'closedActionCentered':True,'openInputLeft16pxFocusedPrefilled':True,'selectionRetained':True}
def consent(s):
 p=s.page;p.locator('[data-intent="continue"]').click();expect(p.locator('.customer-fields')).to_be_visible();no_old_copy(p)
 c=p.locator('.consent');g=c.evaluate("""e=>{const b=n=>{const r=n.getBoundingClientRect(),s=getComputedStyle(n);return{w:r.width,h:r.height,fontSize:s.fontSize,lineHeight:s.lineHeight,minHeight:s.minHeight,padding:s.padding}};const copy=e.querySelector('.consent-copy'),w=document.createTreeWalker(copy,NodeFilter.SHOW_TEXT),tops=[];while(w.nextNode()){let n=w.currentNode;for(let i=0;i<n.length;i++){if(!n.textContent[i].trim())continue;let r=document.createRange();r.setStart(n,i);r.setEnd(n,i+1);tops.push(+r.getBoundingClientRect().top.toFixed(2))}}return{consent:b(e),copy:b(copy),checkboxHit:b(e.querySelector('.consent-check')),legal:[...e.querySelectorAll('button')].map(b),textTops:[...new Set(tops)].sort((a,b)=>a-b)}}""")
 check(g['consent']['fontSize']=='13px' and g['consent']['h']<80 and g['checkboxHit']['w']==44 and g['checkboxHit']['h']==44,'Consent size/hit area wrong');check(all(b['minHeight']=='0px' and b['padding']=='0px' and b['h']<22.2 for b in g['legal']),'Consent legal phrases still tall')
 check(all(abs(b-a-22.1)<.1 for a,b in zip(g['textTops'],g['textTops'][1:])),'Consent sentence line rhythm broken')
 capture(s,'consent','.consent',allcases=True)
 box=c.locator('input');check(box.get_attribute('required') is not None,'Consent no longerrequired');check(box.get_attribute('aria-labelledby')==c.locator('.consent-copy').get_attribute('id'),'Consent accessible label wrong')
 hit=c.locator('.consent-check');hit.click(position={'x':3,'y':3});expect(box).to_be_checked();hit.click(position={'x':3,'y':3});expect(box).not_to_be_checked();c.locator('.consent-copy label').first.click();expect(box).to_be_checked();c.locator('.consent-copy label').last.click();expect(box).not_to_be_checked();box.focus();box.press('Space');expect(box).to_be_checked()
 legal=[]
 for button in c.locator('button').all():
  label=button.inner_text();button.focus();button.press('Enter');expect(p.locator('.legal-dialog[open]')).to_be_visible();no_old_copy(p);focus_trap(p,'.legal-dialog[open]');expect(box).to_be_checked();p.keyboard.press('Escape');expect(button).to_be_focused();expect(box).to_be_checked()
  if s.width<=700:button.tap()
  else:button.click()
  expect(p.locator('.legal-dialog[open]')).to_be_visible();expect(box).to_be_checked();p.keyboard.press('Escape');expect(button).to_be_focused();legal.append({'label':label,'EnterClickTapEscapeFocus':'PASS','consentUnchanged':True})
 s.metrics['consent']={'geometry':g,'interaction':legal,'hitAreaCornerLabelsSpace':'PASS'}
 # One real pointer street selection per case; never submits by field Enter.
 name,phone,address=[p.locator(f'input[name="{n}"]') for n in ['name','phone','address']];name.fill(NAME);name.press('Enter');expect(phone).to_be_focused();phone.fill(PHONE);phone.press('Enter');expect(address).to_be_focused();address.fill('Фонтанская, 12');option=p.locator('.address-suggestions [role="option"]').first;expect(option).to_be_visible()
 if s.width<=700:option.tap()
 else:option.click()
 expected=('Фонтанська дорога' if s.lang=='uk' else 'Фонтанская дорога')+', 12';expect(address).to_have_value(expected);address.press('Enter');check(not s.posts,'FieldEnter attemptedPOST')
 serial=p.evaluate('JSON.stringify({session:{...sessionStorage},local:{...localStorage}})');check(all(x not in serial for x in [NAME,PHONE,expected,REQUESTED]),'Private contact/request fields stored')
 expect(p.locator('[data-intent="submit"]')).to_have_text('Забронювати' if s.lang=='uk' else 'Забронировать');no_old_copy(p)
 if s.width==390:
  draft=json.loads(p.evaluate('sessionStorage.getItem("joyrent.rental-draft.v1")'));s.expected={'console':'ps5','days':3,'startDate':draft['start'],'controllers':2,'gameIds':draft['gameIds'],'name':NAME,'phone':PHONE,'method':'delivery','address':expected,'securityMode':'contract','requestedGame':REQUESTED,'consent':True,'website':'','language':s.lang};check(len(draft['gameIds'])==1,'Fixtureselectionwrong');s.armed=True;p.locator('[data-intent="submit"]').click();expect(p.locator('.booking-success')).to_be_visible();check(len(s.fixtures)==1 and all(s.fixtures[0]['payloadChecks'].values()),'Fixturepayload mismatch');expect(p.locator('.booking-success .muted')).to_contain_text('очікує підтвердження' if s.lang=='uk' else 'ожидает подтверждения');expect(p.locator('.booking-success .muted')).to_contain_text('Оплата ще не потрібна' if s.lang=='uk' else 'Оплата пока не нужна');check(p.evaluate('sessionStorage.getItem("joyrent.rental-draft.v1")') is None,'Successfulfixture didnotclear draft');no_old_copy(p);capture(s,'success','.booking-success',allcases=True)
 qa.nooverflow(p)
def short(browser,width,height):
 with Session(TouchBrowser(browser),f'short-{width}-{height}','uk',width) as s:
  p=s.page;p.set_viewport_size({'width':width,'height':height});p.locator('.game-picker-button').click();p.locator('.game-picker-search input').fill(REQUESTED);p.locator('.game-picker-empty-actions button').filter(has_text='Запросити гру').click();field=p.locator('.missing-game-fields input');expect(field).to_be_focused();expect(field).to_have_value(REQUESTED);frame(p)
  b=qa.box(field);check(b['y']>=0 and b['bottom']<=height,'Shortviewport requestinput outsideviewport');check(field.evaluate('e=>{let r=e.getBoundingClientRect();return document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)===e}'),'Shortviewport input obstructed');no_old_copy(p)
  path=SCREENS/f'uk-{width}x{height}-request.png';p.screenshot(path=str(path),full_page=False);raw=path.read_bytes();screens.append({'file':str(path.relative_to(ROOT)),'phase':'short-request','language':'uk','width':width,'height':height,'sha256':hashlib.sha256(raw).hexdigest(),'accepted':False})
  focus_trap(p,'.game-picker-dialog[open]');done=p.locator('.game-picker-footer .button');target44(done);done.click();expect(p.locator('.game-picker-button')).to_be_focused();expect(p.locator('.requested-game-note')).to_contain_text(REQUESTED);qa.nooverflow(p);s.validate();return{'width':width,'height':height,'status':'PASS','input':b,'focusDone':'PASS','unintendedPOST':s.posts,'pageErrors':s.errors}
def native_pages(browser):
 context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce',service_workers='block');writes=[];errors=[]
 def guard(route):
  r=route.request
  if r.method not in ['GET','HEAD'] or urlparse(r.url).hostname not in ['localhost','127.0.0.1']:writes.append({'method':r.method,'url':r.url});route.abort('blockedbyclient')
  else:route.continue_()
 context.route('**/*',guard);p=context.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 for lang in ['uk','ru']:
  for phase,key in [('faq','faqRuUrl' if lang=='ru' else 'faqUrl'),('terms','termsRuUrl' if lang=='ru' else 'termsUrl'),('privacy','privacyRuUrl' if lang=='ru' else 'privacyUrl')]:
   url=boot[key];check(urlparse(url).hostname=='localhost','NativepageURLdoesnotpointlocal');r=p.goto(url,wait_until='networkidle');check(r.status==200,'NativepageGETfailed');expect(p.locator('html')).to_have_attribute('lang',lang);p.locator('details').evaluate_all('es=>es.forEach(e=>e.open=true)');copy=no_old_copy(p);check(copy['hasBookingTerminology'],'Nativepagehasno booking terminology');qa.nooverflow(p);capture_native(p,lang,phase)
   native.append({'language':lang,'page':phase,'url':url,'GETStatus':r.status,'status':'PASS','copy':copy,'documentSHA256':hashlib.sha256(r.body()).hexdigest()})
 check(not writes and not errors,'Nativepagewrite/error');context.close()
def write_report():
 (OUT/'browser-check.json').write_text(json.dumps({'cases':records,'shortViewportCases':shorts,'nativePages':native,'screens':screens,'physicalSafariVerified':False},ensure_ascii=False,indent=2)+'\n')
with sync_playwright() as pw:
 browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for lang in ['uk','ru']:
  for width in [320,390,1440]:
   record={'language':lang,'width':width,'status':'FAIL','realOrders':0,'realEmails':0}
   try:
    with Session(TouchBrowser(browser),f'final192-{lang}-{width}',lang,width) as s:
     boot=pboot=s.page.evaluate('window.JOYRENT');hero(s);booking(s);picker(s);consent(s);s.validate();record.update(status='PASS',entry=s.entry,metrics=s.metrics,fixtures=s.fixtures,unintendedPOST=s.posts,pageErrors=s.errors,HTTPFailures=s.http)
   except Exception as e:record['error']=str(e);traceback.print_exc()
   records.append(record);write_report();print(json.dumps({k:v for k,v in record.items() if k not in ['entry','metrics','fixtures']},ensure_ascii=False),flush=True)
   if record['status']=='FAIL':raise SystemExit(1)
 for width,height in [(844,220),(320,280)]:shorts.append(short(browser,width,height));write_report();print(json.dumps(shorts[-1]),flush=True)
 native_pages(browser);write_report();browser.close()
print('FINAL targeted6+2/native6 PASS; allwritesblocked excepttwointerceptedfixtures.',flush=True)
