from playwright.sync_api import sync_playwright,expect
from pathlib import Path
from PIL import Image
import json,io
OUT=Path('docs/refinement-1.5');OUT.mkdir(parents=True,exist_ok=True);catalog=json.loads(Path('wordpress/joyrent-rentals/data/catalog.json').read_text())
report={}
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=b.new_page(viewport={'width':1440,'height':1000},reduced_motion='no-preference')
 page.goto('http://localhost:8080');page.wait_for_selector('h1')
 first=page.locator('.hero-word-group').first
 early=first.evaluate('(e)=>({opacity:getComputedStyle(e).opacity,transform:getComputedStyle(e).transform,animation:getComputedStyle(e).animationName,filter:getComputedStyle(e).filter})')
 page.wait_for_timeout(1200)
 final=first.evaluate('(e)=>({opacity:getComputedStyle(e).opacity,animation:getComputedStyle(e).animationName,filter:getComputedStyle(e).filter})')
 assert early['animation']=='joyrent-hero-word-in' and float(early['opacity'])<1
 assert final['opacity']=='1' and final['filter']=='blur(0px)'
 report['heroEntrance']={'early':early,'final':final}
 # Hover moves the card, while the artwork size and transform stay fixed.
 card=page.locator('.game-card').first;card.scroll_into_view_if_needed();button=card.locator('.game-art');image=button.locator('img');image.evaluate('(e)=>e.decode()')
 page.mouse.move(5,5);page.wait_for_timeout(400);before=image.bounding_box();base=card.bounding_box()
 button.hover();page.wait_for_timeout(400);after=image.bounding_box();hover=card.bounding_box()
 assert abs(before['width']-after['width'])<.1 and abs(before['height']-after['height'])<.1
 assert abs(hover['y']-base['y']+5)<.1,(base,hover)
 expect(image).to_have_css('transform','none')
 page.locator('#games').screenshot(path=str(OUT/'games-hover-1440.png'))
 button.click();cover=page.locator('.dialog-cover');cover.evaluate('(e)=>e.decode()');assert abs(cover.bounding_box()['width']-before['width'])<1.5
 page.screenshot(path=str(OUT/'game-dialog-fc27-1440.png'));page.keyboard.press('Escape')
 report['hover']={'shiftPx':hover['y']-base['y'],'coverWidthBefore':before['width'],'coverWidthAfter':after['width'],'coverTransform':'none'}
 page.emulate_media(reduced_motion='reduce');button.hover();page.wait_for_timeout(100)
 expect(first).to_have_css('animation-name','none');expect(card).to_have_css('transform','none')
 report['reducedMotion']='Immediate visible headline; no hover movement'
 page.close()
 # All console-filtered artwork and dialogs, both languages, square and uncropped.
 for lang in ['uk','ru']:
  for width in [390,1440]:
   page=b.new_page(viewport={'width':width,'height':1000},reduced_motion='reduce')
   page.goto('http://localhost:8080'+('/?lang=ru' if lang=='ru' else ''));page.wait_for_selector('h1');page.evaluate('document.fonts.ready')
   for console in ['ps5','ps4']:
    page.locator('#rates').get_by_role('button',name=console.upper(),exact=True).click()
    ids=[g for g in catalog['games'] if console in g['platforms']]
    assert page.locator('.game-card').count()==len(ids)
    for g in ids:
     name=('Детальніше про ' if lang=='uk' else 'Подробнее об игре ')+g['title'];button=page.get_by_role('button',name=name,exact=True)
     button.scroll_into_view_if_needed();im=button.locator('img');im.evaluate('(e)=>e.decode()')
     assert im.evaluate('(e)=>e.naturalWidth===720&&e.naturalHeight===720')
     source=im.bounding_box();button.click();dialog=page.locator('.game-dialog');cover=dialog.locator('.dialog-cover');cover.evaluate('(e)=>e.decode()');box=cover.bounding_box()
     assert abs(box['width']-source['width'])<1.5 and abs(box['width']-box['height'])<1.5,(width,g['id'],source,box)
     assert dialog.evaluate('(e)=>e.scrollWidth<=e.clientWidth')
     if g['id']=='stalker2' and lang=='uk':page.screenshot(path=str(OUT/f'game-dialog-stalker2-{width}.png'))
     page.keyboard.press('Escape');expect(button).to_be_focused()
   page.close()
 report['artworkAndDialogs']='Every supported cover/dialog in UA/RU at 390 and 1440: square, same width, focus restored'
 # Native browser render frames of the word entrance, cropped only for the evidence GIF.
 page=b.new_page(viewport={'width':1440,'height':1000},reduced_motion='no-preference')
 page.goto('http://localhost:8080');page.wait_for_selector('h1');frames=[];bounds=page.locator('h1').bounding_box()
 for i in range(14):
  frames.append(Image.open(io.BytesIO(page.screenshot(clip={'x':bounds['x']-12,'y':bounds['y']-16,'width':bounds['width']+24,'height':bounds['height']+32}))).convert('RGB'));page.wait_for_timeout(45)
 frames.extend([frames[-1]]*10);frames[0].save(OUT/'hero-text-reveal.gif',save_all=True,append_images=frames[1:],duration=85,loop=0)
 page.close();b.close()
OUT.joinpath('interaction-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print('PASS: word reveal, static reduced motion, whole-card hover, unchanged cover dimensions and all game dialogs in both languages')
