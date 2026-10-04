"""Real browser regression: stable desktop panels without clipping text or changing covers."""
import json, os, urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
ROOT=Path(__file__).resolve().parents[2]
BASE=os.getenv('JOYRENT_TEST_URL','http://localhost:8080')
OUT=ROOT/'docs/refinement-1.9.5'
OUT.mkdir(parents=True,exist_ok=True);(OUT/'screens').mkdir(exist_ok=True)
rows=[];screens=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for lang,width,height in [('uk',1440,1000),('ru',1440,1000),('uk',390,844),('ru',320,740),('uk',768,1000),('ru',768,430)]:
  ctx=browser.new_context(viewport={'width':width,'height':height},reduced_motion='reduce')
  page=ctx.new_page();errors=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  ctx.route('**/*',lambda r:r.continue_() if r.request.method in ('GET','HEAD') else r.abort())
  page.goto(BASE+'/'+('?lang=ru' if lang=='ru' else ''),wait_until='networkidle');page.evaluate('document.fonts.ready')
  for console in ['ps5','ps4']:
   page.locator('#rates').get_by_role('button',name=console.upper(),exact=True).click()
   openers=page.locator('#games .game-art');panels=[]
   for i in range(openers.count()):
    opener=openers.nth(i);opener.scroll_into_view_if_needed();expected_width=opener.evaluate('e=>e.clientWidth');opener.click()
    dialog=page.locator('.game-dialog');expect(dialog).to_be_visible()
    cover=dialog.locator('.dialog-cover');cover.evaluate('e=>e.decode()')
    metrics=dialog.evaluate('''e=>{const q=s=>e.querySelector(s),r=q('.dialog-copy').getBoundingClientRect(),b=q('.button').getBoundingClientRect();return {title:q('h2').textContent,height:r.height,buttonBottom:r.bottom-b.bottom,stacked:e.classList.contains('is-stacked'),copyDisplay:getComputedStyle(q('.dialog-copy')).display,copyOverflow:getComputedStyle(q('.dialog-copy')).overflowY,scrollWidth:e.scrollWidth,clientWidth:e.clientWidth}}''')
    box=cover.bounding_box();assert abs(box['width']-expected_width)<1.5,(metrics,box,expected_width)
    assert abs(box['width']-box['height'])<1.5,(metrics,box)
    assert metrics['scrollWidth']<=metrics['clientWidth']+1,metrics
    assert page.evaluate('document.documentElement.scrollWidth')<=width
    assert dialog.locator('h2').evaluate('e=>e.scrollHeight<=e.clientHeight+1')
    assert dialog.locator('h2 + p').evaluate('e=>e.scrollHeight<=e.clientHeight+1')
    if width>700 and height>=560 and not metrics['stacked']:
     panels.append(metrics['height']);assert metrics['height']>=440
    else:
     assert metrics['copyDisplay']=='block',metrics
    if console=='ps5' and metrics['title'] in ['EA SPORTS FC 27','Grand Theft Auto V','S.T.A.L.K.E.R. 2: Heart of Chornobyl'] and width in [1440,390] and lang=='uk':
     filename=f'{lang}-{width}-{i}.png';dialog.screenshot(path=str(OUT/'screens'/filename));screens.append(filename)
    dialog.locator('.button').scroll_into_view_if_needed();expect(dialog.locator('.button')).to_be_visible()
    page.keyboard.press('Tab');assert page.evaluate('document.activeElement.closest(".game-dialog")!==null')
    page.keyboard.press('Escape');expect(opener).to_be_focused()
    rows.append({'language':lang,'viewport':[width,height],'console':console,**metrics,'coverWidth':box['width']})
   if panels:assert max(panels)-min(panels)<1,(lang,width,console,panels)
  # Details opened inside the optional-game picker retain nested close and focus.
  page.locator('#booking').get_by_role('button',name='Обрати ігри' if lang=='uk' else 'Выбрать игры',exact=True).click()
  picker=page.locator('.game-picker-dialog');expect(picker).to_be_visible()
  opener=picker.locator('.game-art').first;opener.click();detail=page.locator('.game-dialog');expect(detail).to_be_visible()
  detail.locator('.button').scroll_into_view_if_needed();expect(detail.locator('.button')).to_be_visible()
  page.keyboard.press('Escape');expect(picker).to_be_visible();expect(opener).to_be_focused()
  page.keyboard.press('Escape');expect(picker).to_have_count(0)
  assert not errors,errors
  ctx.close()
 # A long custom description grows beyond the minimum instead of being hidden/clamped.
 with urllib.request.urlopen(BASE.rstrip('/')+'/wp-json/joyrent/v1/catalog') as response:catalog=json.load(response)
 for lang in ['uk','ru']:
  ctx=browser.new_context(viewport={'width':1440,'height':900},reduced_motion='reduce')
  page=ctx.new_page();custom=json.loads(json.dumps(catalog));game=custom['games'][0]
  game['title']='Custom title '+('A long title ' * 12);game['description']='Довгий опис. '*140;game['descriptionRu']='Длинное описание. '*140
  ctx.route('**/joyrent/v1/catalog',lambda r:r.fulfill(status=200,content_type='application/json',body=json.dumps(custom)))
  page.goto(BASE+'/'+('?lang=ru' if lang=='ru' else ''),wait_until='networkidle');page.evaluate('document.fonts.ready')
  opener=page.locator('#games .game-art').first;opener.scroll_into_view_if_needed();opener.click()
  dialog=page.locator('.game-dialog');expect(dialog).to_be_visible();panel=dialog.locator('.dialog-copy')
  assert panel.bounding_box()['height']>440
  assert dialog.locator('h2').text_content()==game['title']
  assert dialog.locator('h2 + p').inner_text()==game['descriptionRu' if lang=='ru' else 'description'].strip()
  assert dialog.locator('h2 + p').evaluate('e=>e.scrollHeight<=e.clientHeight+1')
  dialog.locator('.button').scroll_into_view_if_needed();expect(dialog.locator('.button')).to_be_visible()
  page.keyboard.press('Escape');expect(opener).to_be_focused();ctx.close()
 # Reaching the configured game limit keeps the explanation and disabled CTA visible.
 for lang,width,height in [('uk',1440,900),('ru',390,844)]:
  ctx=browser.new_context(viewport={'width':width,'height':height},reduced_motion='reduce')
  page=ctx.new_page();limited=json.loads(json.dumps(catalog));limited['settings']['maxGames']=1
  ctx.route('**/*',lambda r:r.continue_() if r.request.method in ('GET','HEAD') else r.abort())
  ctx.route('**/joyrent/v1/catalog',lambda r:r.fulfill(status=200,content_type='application/json',body=json.dumps(limited)))
  page.goto(BASE+'/'+('?lang=ru' if lang=='ru' else ''),wait_until='networkidle');page.evaluate('document.fonts.ready')
  first=page.locator('#games .game-art').first;first.scroll_into_view_if_needed();first.click()
  dialog=page.locator('.game-dialog');expect(dialog.locator('.button')).to_be_enabled();dialog.locator('.button').click()
  opener=page.locator('#games .game-art').nth(1);opener.scroll_into_view_if_needed();opener.click()
  dialog=page.locator('.game-dialog');notice=dialog.locator('.game-selection-notice');button=dialog.locator('.button')
  expect(notice).to_have_text('Досягнуто ліміту ігор. Прибери одну, щоб додати іншу.' if lang=='uk' else 'Достигнут лимит игр. Убери одну, чтобы добавить другую.')
  assert notice.evaluate('e=>e.scrollHeight<=e.clientHeight+1');button.scroll_into_view_if_needed();expect(button).to_be_visible();expect(button).to_be_disabled()
  if width>700:
   gap=notice.bounding_box()['y']-(dialog.locator('.game-meta').bounding_box()['y']+dialog.locator('.game-meta').bounding_box()['height'])
   assert abs(gap-18)<1,gap
  page.keyboard.press('Escape');expect(opener).to_be_focused();ctx.close()
 browser.close()
(OUT/'game-detail-layout.json').write_text(json.dumps({'status':'PASS','dialogCases':len(rows),'cases':rows,'screenshots':screens,'nestedPickerCases':6,'customLongCopyCases':2,'gameLimitCases':2,'realOrdersOrMail':0},ensure_ascii=False,indent=2)+'\n')
print(f'PASS: {len(rows)} game detail cases, six nested picker cases, two long custom descriptions, two game-limit cases; covers and text preserved')
