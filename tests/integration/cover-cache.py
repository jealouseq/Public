import json
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
out=Path('work/verification-1.9.3')
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 ctx=b.new_context(viewport={'width':1440,'height':900},device_scale_factor=2,reduced_motion='reduce')
 page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.route('**/*',lambda r:r.continue_() if r.request.method in ('GET','HEAD') else r.abort())
 page.goto('http://localhost:8080/',wait_until='networkidle');page.locator('#games').scroll_into_view_if_needed()
 card=page.locator('#games .game-card').first;expect(card).to_be_visible()
 image=card.locator('img');expect(image).to_be_visible();page.wait_for_function("document.querySelector('#games .game-card img')?.naturalWidth>0")
 src=image.evaluate('e=>e.currentSrc');assert '-720.webp' in src,src
 card.locator('button').first.click();expect(page.locator('.game-dialog')).to_be_visible()
 detail=page.locator('.game-dialog img');expect(detail).to_be_visible();page.wait_for_timeout(100)
 assert detail.evaluate('e=>e.currentSrc')==src
 entries=page.evaluate('(url)=>performance.getEntriesByName(url).map(e=>({url:e.name,encoded:e.encodedBodySize,transfer:e.transferSize}))',src)
 assert len([e for e in entries if e['transfer']>0])==1,entries
 assert not errors,errors
 (out/'cover-cache.json').write_text(json.dumps({'status':'PASS','DPR':2,'cardAndDetailURL':src,'resourceEntries':entries,'errors':errors,'liveWrites':0},indent=2)+'\n')
 print('PASS: Retina game card and detail reuse one 720px URL, one transferred resource')
 ctx.close();b.close()
