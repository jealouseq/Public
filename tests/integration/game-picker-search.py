from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright,expect
import json,sys
root=Path(__file__).resolve().parents[2]
cat=json.loads((root/'wordpress/joyrent-rentals/data/catalog.json').read_text())
cat.update(currency='UAH',acceptingRequests=True,settings={})
results=[]
try:
 with sync_playwright() as pw:
  browser=pw.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
  for lang in ['uk','ru']:
   ctx=browser.new_context(viewport={'width':390,'height':844},has_touch=True,reduced_motion='reduce')
   def route(r):
    if r.request.method not in ['GET','HEAD']:r.abort()
    elif r.request.url.endswith('/catalog'):r.fulfill(status=200,content_type='application/json',body=json.dumps(cat))
    elif urlparse(r.request.url).hostname not in ['localhost','127.0.0.1']:r.abort()
    else:r.continue_()
   ctx.route('**/*',route)
   p=ctx.new_page();p.goto('http://localhost:5177/'+('?lang=ru' if lang=='ru' else ''))
   p.wait_for_function('document.querySelectorAll(".tariff").length===4')
   p.evaluate('document.fonts.ready')
   p.locator('.game-picker-button').click()
   p.locator('.game-picker-search input').fill('QA missing game 2026')
   expect(p.locator('.picker-game')).to_have_count(0)
   # The regression is the duplicate missing-game entry in the empty-results box.
   expect(p.locator('.game-picker-empty button')).to_have_count(0)
   toggle=p.locator('.game-picker-request .missing-game-toggle')
   expect(toggle).to_be_visible();toggle.click()
   field=p.locator('.missing-game-fields input')
   expect(field).to_have_value('QA missing game 2026')
   expect(field).to_be_focused()
   expect(p.locator('.game-picker-footer .button')).to_be_visible()
   field.fill('My requested game')
   p.locator('.game-picker-footer .button').click()
   expect(p.locator('.booking-games')).to_contain_text('My requested game')
   p.locator('.game-picker-button').click()
   expect(p.locator('.missing-game-fields input')).to_have_value('My requested game')
   results.append({'language':lang,'singleRequestEntry':True,'queryPrefillAndFocus':True,'doneVisible':True,'requestPreserved':True})
   ctx.close()
  browser.close()
except Exception as error:
 print(json.dumps({'status':'FAIL','completed':results,'error':str(error)},ensure_ascii=False))
 sys.exit(1)
print(json.dumps({'status':'PASS','cases':results},ensure_ascii=False,indent=2))
