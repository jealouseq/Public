"""Safe browser regressions: local source only; all writes intercepted before transmission."""
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright,expect
import json,sys,os
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'work/frontend-fixes'
OUT.mkdir(parents=True,exist_ok=True)
phase=sys.argv[1] if len(sys.argv)>1 else 'green'
base_url=os.environ.get('JOYRENT_CHECK_BASE','http://localhost:5177/')
catalog=json.loads((ROOT/'wordpress/joyrent-rentals/data/catalog.json').read_text())
catalog.update(currency='UAH',acceptingRequests=True,settings={})
results=[]
def create(browser,lang='uk',width=390,hang_first=False):
 ctx=browser.new_context(viewport={'width':width,'height':844},device_scale_factor=2,has_touch=width==390,is_mobile=width==390,reduced_motion='reduce')
 posts=[];errors=[]
 ctx.add_init_script("window.JOYRENT={apiBase:'/audit-api',assetBase:''}")
 def route(r):
  if r.request.method!='GET':
   if r.request.url.endswith('/audit-api/requests'):
    posts.append(r.request.post_data_json)
    if hang_first and len(posts)==1:return
    r.fulfill(status=503,content_type='application/json',body=json.dumps({'code':'jr_create','message':'Не вдалося прийняти бронювання. Спробуй ще раз трохи пізніше.'}))
   else:r.abort()
  elif r.request.url.endswith('/audit-api/catalog'):r.fulfill(status=200,content_type='application/json',body=json.dumps(catalog))
  elif urlparse(r.request.url).hostname not in ['localhost','127.0.0.1']:r.abort()
  else:r.continue_()
 ctx.route('**/*',route);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto(base_url+'?lang='+lang);page.evaluate('document.fonts.ready')
 expect(page.locator('[data-intent="continue"]')).to_be_enabled();page.locator('[data-intent="continue"]').click()
 page.locator('[name="name"]').fill('QA Local');page.locator('[name="phone"]').fill('+380500000000')
 return ctx,page,posts,errors
def frames(page):page.evaluate('()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))')
def touch_case(browser):
 ctx,page,posts,errors=create(browser);field=page.locator('[name="address"]');field.fill('Фонта')
 expect(page.locator('[role="option"]').nth(1)).to_be_visible();page.locator('[role="option"]').nth(1).tap();frames(page)
 data={'address':field.input_value(),'openDialogs':page.locator('dialog[open]').count(),'posts':len(posts),'errors':errors}
 page.screenshot(path=str(OUT/f'{phase}-touch.png'))
 if data['openDialogs']==0:
  page.locator('.consent button').nth(1).tap();expect(page.locator('.legal-dialog')).to_be_visible();data['explicitPrivacyTapWorks']=True
 ctx.close()
 assert data['address']=='Середньофонтанська вулиця',data
 assert data['openDialogs']==0,data
 assert not data['posts'] and not errors,data
 return data
def address_input_modes(browser):
 modes=[]
 for mode in ['mouse','keyboard','accessibility-click','blur-during-touch','pan-y-cancel']:
  ctx,page,posts,errors=create(browser,width=1440 if mode=='mouse' else 390);field=page.locator('[name="address"]');field.fill('ка' if mode=='pan-y-cancel' else 'Фонта')
  expect(page.locator('[role="option"]').first).to_be_visible()
  if mode=='mouse':page.locator('[role="option"]').nth(1).click()
  elif mode=='keyboard':field.press('ArrowDown');field.press('ArrowDown');field.press('Enter')
  elif mode=='accessibility-click':page.locator('[role="option"]').nth(1).dispatch_event('click',{'detail':0})
  else:
   cdp=ctx.new_cdp_session(page)
   target=page.locator('[role="option"]').nth(1) if mode=='blur-during-touch' else page.locator('[role="listbox"]')
   box=target.bounding_box();x=box['x']+box['width']/2;y=box['y']+box['height']/2 if mode=='blur-during-touch' else box['y']+box['height']-25
   cdp.send('Input.dispatchTouchEvent',{'type':'touchStart','touchPoints':[{'x':x,'y':y,'id':1}]})
   if mode=='blur-during-touch':
    field.evaluate('e=>e.blur()');assert page.locator('[role="option"]').count()>0,'Option removed during pointer gesture'
   else:
    for delta in [20,45,80]:cdp.send('Input.dispatchTouchEvent',{'type':'touchMove','touchPoints':[{'x':x,'y':y-delta,'id':1}]});frames(page)
   cdp.send('Input.dispatchTouchEvent',{'type':'touchEnd','touchPoints':[]})
  frames(page);data={'mode':mode,'value':field.input_value(),'dialogs':page.locator('dialog[open]').count(),'errors':errors,'posts':len(posts)}
  if mode=='pan-y-cancel':
   data['scrollTop']=page.locator('[role="listbox"]').evaluate('e=>e.scrollTop');data['touchAction']=page.locator('[role="option"]').first.evaluate('e=>getComputedStyle(e).touchAction')
   assert data['value']=='ка' and data['scrollTop']>0 and data['touchAction']=='pan-y',data
  else:assert data['value']=='Середньофонтанська вулиця',data
  assert not data['dialogs'] and not errors and not posts,data
  modes.append(data);ctx.close()
 return modes
def phone_case(browser,lang='uk'):
 ctx,page,posts,errors=create(browser,lang);page.locator('[name="address"]').fill('Фонтанська дорога, 10');phone=page.locator('[name="phone"]');phone.fill('abcdefghij');page.locator('.consent input').check();page.locator('[data-intent="submit"]').click();frames(page)
 data={'posts':len(posts),'focused':phone.evaluate('e=>e===document.activeElement'),'invalid':phone.get_attribute('aria-invalid'),'describedby':phone.get_attribute('aria-describedby'),'errors':errors}
 if data['describedby']:data['inlineError']=page.locator('[id="'+data['describedby']+'"]').inner_text()
 page.screenshot(path=str(OUT/f'{phase}-phone-{lang}.png'));ctx.close()
 assert data['posts']==0,data
 assert data['focused'] and data['invalid']=='true' and data.get('inlineError'),data
 assert ('український' if lang=='uk' else 'украинский') in data['inlineError'],data
 assert not errors,data
 return data
def retry_case(browser):
 ctx,page,posts,errors=create(browser);page.locator('[name="address"]').fill('Фонтанська дорога, 10');page.locator('.consent input').check()
 for i in range(2):page.locator('[data-intent="submit"]').click();expect(page.locator('.form-error')).to_be_visible();expect(page.locator('[data-intent="submit"]')).to_be_enabled()
 page.locator('[name="name"]').fill('  QA Local  ');page.locator('[name="phone"]').fill('+380 (50) 000-00-00');page.locator('[name="address"]').fill('  Фонтанська дорога, 10  ')
 page.locator('[data-intent="submit"]').click();expect(page.locator('.form-error')).to_be_visible();expect(page.locator('[data-intent="submit"]')).to_be_enabled()
 page.locator('[name="phone"]').fill('+380500000001');page.locator('[data-intent="submit"]').click();expect(page.locator('.form-error')).to_be_visible();expect(page.locator('[data-intent="submit"]')).to_be_enabled()
 ids=[p['requestId'] for p in posts];data={'requestIds':ids,'phones':[p['phone'] for p in posts],'focusedError':page.locator('.form-error').evaluate('e=>e===document.activeElement'),'errors':errors}
 page.screenshot(path=str(OUT/f'{phase}-retry-error.png'));ctx.close()
 assert len(ids)==4 and len(set(ids[:3]))==1 and ids[3]!=ids[0],data
 assert data['focusedError'],data
 assert not errors,data
 return data
def timeout_retry_case(browser):
 ctx,page,posts,errors=create(browser,hang_first=True);page.locator('[name="address"]').fill('Фонтанська дорога, 10');page.locator('.consent input').check();page.clock.install()
 page.locator('[data-intent="submit"]').click();expect(page.locator('[data-intent="submit"]')).to_be_disabled();page.clock.fast_forward(30_001)
 expect(page.locator('.form-error')).to_be_visible();expect(page.locator('[data-intent="submit"]')).to_be_enabled()
 message=page.locator('.form-error').inner_text();first_id=posts[0]['requestId'];page.locator('[data-intent="submit"]').click();expect(page.locator('.form-error')).to_be_visible();expect(page.locator('[data-intent="submit"]')).to_be_enabled()
 data={'message':message,'requestIds':[p['requestId'] for p in posts],'focusedError':page.locator('.form-error').evaluate('e=>e===document.activeElement'),'errors':errors};ctx.close()
 assert 'тими самими даними' in message and len(posts)==2 and posts[1]['requestId']==first_id,data
 assert not errors,data
 return data
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
 for name,fn in [('touch-clickthrough',touch_case),('phone-uk',phone_case),('phone-ru',lambda b:phone_case(b,'ru')),('canonical-retry',retry_case),('address-input-modes',address_input_modes),('timeout-retry',timeout_retry_case)]:
  try:results.append({'case':name,'pass':True,'data':fn(browser)})
  except Exception as e:results.append({'case':name,'pass':False,'error':str(e)})
 browser.close()
(OUT/f'{phase}-browser.json').write_text(json.dumps(results,ensure_ascii=False,indent=2));print(json.dumps(results,ensure_ascii=False,indent=2))
assert all(c['pass'] for c in results),f'{sum(not c["pass"] for c in results)} regressions failed'
