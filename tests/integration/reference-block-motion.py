"""Check the consolidated kit and reference motion on local WordPress only."""
import json, os, traceback, urllib.request
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright, expect

ROOT=Path(__file__).resolve().parents[2]
BASE=os.getenv('JOYRENT_TEST_URL','http://localhost:8080').rstrip('/')
assert urlparse(BASE).hostname in ['localhost','127.0.0.1']
PHASE=os.getenv('JOYRENT_TEST_PHASE','green')
OUT=ROOT/'work/refinement-1.9.11'; OUT.mkdir(parents=True,exist_ok=True)
with urllib.request.urlopen(BASE+'/wp-json/joyrent/v1/catalog') as r: catalog=json.load(r)
rows=[]
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
    cases=[('uk',390,'no-preference')] if PHASE=='red' else [(lang,w,m) for lang in ['uk','ru'] for w in [320,390,768,844,1024,1440] for m in ['reduce','no-preference']]
    for lang,width,motion in cases:
        height=568 if width==320 else 390 if width==844 else 844 if width<701 else 1000
        context=browser.new_context(viewport={'width':width,'height':height},
            reduced_motion=motion,is_mobile=width<701,has_touch=width<701)
        errors,writes=[],[]
        def route(r):
            if r.request.method not in ['GET','HEAD']:
                writes.append(r.request.url);r.abort()
            elif urlparse(r.request.url).hostname not in ['localhost','127.0.0.1']:r.abort()
            else:r.continue_()
        context.route('**/*',route)
        page=context.new_page();page.set_default_timeout(6000)
        page.on('pageerror',lambda e:errors.append(str(e)))
        try:
            page.goto(BASE+('/?lang=ru' if lang=='ru' else '/'),wait_until='networkidle')
            expect(page.locator('.game-picker-button')).to_be_enabled()
            assert page.locator('.console-showcase').count()==0, 'Redundant console showcase remains'
            kit=page.locator('#kit');kit.scroll_into_view_if_needed()
            expect(kit).to_contain_text('EA Play');expect(kit).to_contain_text('PS Plus Deluxe')
            assert kit.inner_text().count('EA Play')==1 and kit.inner_text().count('PS Plus Deluxe')==1
            stage=kit.locator('.kit-product-stage')
            stage.scroll_into_view_if_needed()
            controller=stage.locator('.kit-controller-image')
            expect(controller).to_be_visible()
            assert 'dualsense-cutout' in controller.get_attribute('src')
            expect(stage.locator('.kit-subscription-image')).to_have_count(2)
            expect(stage.locator('.kit-game-box')).to_have_count(2)
            expect(stage.locator('.kit-game-disc')).to_have_count(0)
            stage.evaluate('e=>Promise.all([...e.querySelectorAll("img")].map(i=>i.decode()))')
            assert stage.locator('img').evaluate_all('images=>images.every(i=>i.complete&&i.naturalWidth>0)')
            moving=stage.locator('.kit-element-motion')
            expect(moving).to_have_count(5)
            expect(stage).to_have_attribute('data-motion','running' if motion=='no-preference' else 'paused')
            if motion=='no-preference':
                for item in moving.all():expect(item).to_have_css('animation-play-state','running')
                # Compare actual transforms: each requested object moves; paired objects share timing.
                before=moving.evaluate_all('els=>els.map(e=>getComputedStyle(e).transform)')
                page.wait_for_timeout(350)
                after=moving.evaluate_all('els=>els.map(e=>getComputedStyle(e).transform)')
                assert all(a!=b for a,b in zip(before,after)), (before,after)
                page.emulate_media(reduced_motion='reduce')
            for item in moving.all():expect(item).to_have_css('animation-name','none')
            assert moving.evaluate_all('els=>els.every(e=>getComputedStyle(e).transform!=="none")'), 'Reduced motion removed the staged kit arrangement'
            bounds=stage.bounding_box()
            for item in moving.all():
                b=item.bounding_box()
                assert b['x']>=bounds['x']-2 and b['x']+b['width']<=bounds['x']+bounds['width']+2
                assert b['y']>=bounds['y']-2 and b['y']+b['height']<=bounds['y']+bounds['height']+2
            page.emulate_media(reduced_motion='no-preference')
            expect(stage).to_have_attribute('data-motion','running')
            page.locator('.rental-process').scroll_into_view_if_needed()
            expect(stage).to_have_attribute('data-motion','paused')
            for item in moving.all():expect(item).to_have_css('animation-play-state','paused')
            process=page.locator('.rental-process')
            expect(process).to_have_attribute('data-motion','running')
            cards=process.locator('.rental-step-surface');expect(cards).to_have_count(4)
            boxes=[c.bounding_box() for c in cards.all()]
            if width<701:
                assert abs(boxes[0]['y']-boxes[1]['y'])<1 and boxes[2]['y']>boxes[0]['y'], boxes
                assert max(b['height'] for b in boxes)<=310, boxes
            for c in cards.all(): assert c.evaluate('e=>e.scrollWidth<=e.clientWidth')
            page.emulate_media(reduced_motion='reduce')
            expect(process).to_have_attribute('data-motion','paused')
            for art in process.locator('.step-visual-motion').all():expect(art).to_have_css('animation-name','none')
            # Removing the redundant banner must retain the real PS4 tariffs and booking choice.
            page.locator('#rates .console-switch button').last.click()
            if motion == 'no-preference':
                prefix = 'Обрати PS5' if lang == 'uk' else 'Выбрать PS5'
                outgoing = page.locator(f'#rates .tariff-link[aria-label^="{prefix}"]')
                for button in outgoing.all():
                    assert button.is_disabled(), 'Disappearing PS5 tariff can select a stale price for PS4'
            expect(page.locator('#rates .tariff')).to_have_count(len(catalog['tariffs']['ps4']))
            page.locator('#rates .tariff-link').first.focus();page.keyboard.press('Enter')
            expect(page.locator('#booking .console-switch button').last).to_have_attribute('aria-pressed','true')
            assert str(catalog['tariffs']['ps4'][0]['price']) in ''.join(page.locator('.summary-price').inner_text().split())
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
            assert not page.locator('main .faq-section').count()
            assert not errors and not writes, (errors,writes)
            rows.append({'language':lang,'width':width,'motion':motion,'pass':True})
        except Exception:
            rows.append({'language':lang,'width':width,'motion':motion,'pass':False,'error':traceback.format_exc()})
        finally:context.close()
    browser.close()
(OUT/f'{PHASE}-reference-motion-check.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
for row in rows:print(row['language'],row['width'],row['motion'],'PASS' if row['pass'] else row['error'])
assert all(r['pass'] for r in rows)
