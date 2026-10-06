"""Verify the selected hero scene locally without sending bookings.

The entrance must finish at transform:none, and its separate raster light must
pause out of view, in a hidden document, and for reduced-motion preferences.
"""
import json, os, traceback
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
BASE = os.getenv('JOYRENT_TEST_URL', 'http://localhost:8080').rstrip('/')
assert urlparse(BASE).hostname in ['localhost', '127.0.0.1'], 'Local inspection only'
OUT = ROOT / os.getenv('JOYRENT_EVIDENCE_DIR', 'work/hero-composition-check')
OUT.mkdir(parents=True, exist_ok=True)
MEDIA = json.loads((ROOT / 'wordpress/joyrent/assets/images/hero-media.json').read_text())
rows = []
cases = [(lang, width, motion, False) for lang in ['uk', 'ru'] for width in [390, 1440]
         for motion in ['no-preference', 'reduce']]
cases += [('uk', width, 'no-preference', False) for width in [320, 901, 1536, 3000]]
cases += [('uk', 390, 'no-preference', True)]

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for lang, width, motion, save_data in cases:
        height = 844 if width < 701 else 1080 if width >= 1920 else 900
        dpr = 3 if width < 701 else 2 if width < 1920 else 1
        context = browser.new_context(viewport={'width': width, 'height': height},
                                      device_scale_factor=dpr, reduced_motion=motion,
                                      is_mobile=width < 701, has_touch=width < 701)
        errors, writes, seen = [], [], []

        def route(request):
            if request.request.method not in ['GET', 'HEAD']:
                writes.append(request.request.url)
                request.abort()
            elif urlparse(request.request.url).hostname not in ['localhost', '127.0.0.1']:
                request.abort()
            else:
                request.continue_()

        context.route('**/*', route)
        if save_data:
            context.add_init_script('Object.defineProperty(navigator,"connection",{configurable:true,value:{saveData:true}})')
        context.add_init_script("""window.heroEntranceFrames=[];
            document.addEventListener('animationstart', event => {
              if (event.animationName !== 'joyrent-hero-scene-in') return;
              const node=event.target;
              for (const delay of [0,160,360]) setTimeout(() => {
                const transform=getComputedStyle(node).transform;
                window.heroEntranceFrames.push({delay,transform,
                  y:transform==='none'?0:new DOMMatrixReadOnly(transform).m42});
              },delay);
            });""")
        page = context.new_page()
        page.set_default_timeout(7000)
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.on('request', lambda request: seen.append(request.url))
        try:
            page.goto(BASE + ('/?lang=ru' if lang == 'ru' else '/'), wait_until='networkidle')
            stage = page.locator('.hero-product-stage')
            stage.scroll_into_view_if_needed()
            photo = stage.locator('.hero-photo')
            expect(photo).to_have_count(1)
            expect(photo).to_be_visible()
            photo.evaluate('image=>image.decode()')
            expect(photo).to_have_css('transform', 'none')
            expect(photo).to_have_css('filter', 'none')
            scene = stage.locator('.hero-product-scene')
            expect(scene).to_have_attribute('data-ready', 'true')
            expect(scene).to_have_attribute('data-reveal', 'settled')
            expect(scene).to_have_css('transform', 'none')
            expect(scene).to_have_css('animation-name', 'none')
            frames = page.evaluate('window.heroEntranceFrames')
            if motion == 'no-preference':
                assert len(frames) == 3 and frames[0]['y'] >= 5, frames
                assert frames[-1]['y'] < frames[0]['y'] - 3, frames
            else:
                assert not frames, frames
            picture = stage.locator('.hero-product-picture')
            expect(picture).to_have_css('transform', 'none')
            expect(picture).to_have_css('mask-image', 'none')
            for layer in stage.locator('picture').all():
                assert layer.evaluate('e=>getComputedStyle(e,"::after").content') == 'none'
                assert layer.evaluate('e=>getComputedStyle(e,"::after").animationName') == 'none'
            picture_box, stage_box = picture.bounding_box(), stage.bounding_box()
            assert 'linear-gradient' in stage.evaluate('e=>getComputedStyle(e).maskImage')
            expect(stage).to_have_css('filter', 'none')
            assert abs(picture_box['width'] - stage_box['width']) < 1
            assert abs(picture_box['height'] - stage_box['height']) < 1
            assert stage_box['width'] <= (440 if width <= 900 else 720) + 1
            before = photo.bounding_box()
            image_info = photo.evaluate('''async image => {
                const bitmap = await createImageBitmap(await (await fetch(image.currentSrc)).blob());
                const info = {source: image.currentSrc, sourcePixels: bitmap.width,
                              renderedWidth: image.getBoundingClientRect().width, dpr: devicePixelRatio};
                bitmap.close(); return info;
            }''')
            assert image_info['sourcePixels'] >= image_info['renderedWidth'] * dpr - 1, image_info
            page.wait_for_timeout(350)
            assert before == photo.bounding_box(), 'The settled product photograph is still moving'
            light = stage.locator('.hero-product-light')
            expect(light).to_have_count(1 if MEDIA.get('light') and not save_data else 0)
            if save_data:
                assert not any('hero-slim-light' in request for request in seen), 'Data Saver fetched decorative light'
            if MEDIA.get('light') and not save_data:
                light.locator('img').evaluate('image=>image.decode()')
                if motion == 'no-preference':
                    expect(stage).to_have_attribute('data-motion', 'running')
                    expect(light).to_have_css('animation-play-state', 'running')
                    animation = light.evaluate('e=>e.getAnimations()[0].currentTime')
                    page.wait_for_timeout(350)
                    assert light.evaluate('e=>e.getAnimations()[0].currentTime') > animation + 200
                    page.evaluate('Object.defineProperty(document,"hidden",{configurable:true,get:()=>true});document.dispatchEvent(new Event("visibilitychange"))')
                    expect(stage).to_have_attribute('data-motion', 'paused')
                    expect(light).to_have_css('animation-play-state', 'paused')
                    page.evaluate('delete document.hidden;document.dispatchEvent(new Event("visibilitychange"))')
                    expect(stage).to_have_attribute('data-motion', 'running')
                    expect(light).to_have_css('animation-play-state', 'running')
                    page.locator('#kit').scroll_into_view_if_needed()
                    expect(stage).to_have_attribute('data-motion', 'paused')
                    expect(light).to_have_css('animation-play-state', 'paused')
                    stage.scroll_into_view_if_needed()
                    expect(stage).to_have_attribute('data-motion', 'running')
                    expect(scene).to_have_attribute('data-reveal', 'settled')
                    assert page.evaluate('window.heroEntranceFrames.length') == 3, 'The entrance replayed'
                    page.emulate_media(reduced_motion='reduce')
                expect(stage).to_have_attribute('data-motion', 'paused')
                expect(light).to_have_css('animation-name', 'none')
                expect(scene).to_have_css('transform', 'none')
            button = page.locator('.hero-primary')
            expect(button).to_have_attribute('href', '#rates')
            button_box = button.bounding_box()
            if width <= 700:
                assert 44 <= button_box['height'] <= 60 and button_box['width'] <= 240
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
            assert not errors and not writes, (errors, writes)
            rows.append({'language': lang, 'width': width, 'dpr': dpr, 'motion': motion, 'saveData': save_data,
                         'pass': True, 'entranceFrames': frames,
                         'source': photo.get_attribute('src'), 'imageDensity': image_info, 'stage': stage_box})
        except Exception:
            rows.append({'language': lang, 'width': width, 'dpr': dpr, 'motion': motion, 'saveData': save_data,
                         'pass': False, 'error': traceback.format_exc()})
        finally:
            context.close()
    browser.close()

(OUT / 'hero-motion-check.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2) + '\n')
for row in rows:
    print(row['language'], row['width'], row['motion'], 'Data Saver' if row['saveData'] else '', 'PASS' if row['pass'] else row['error'])
assert all(row['pass'] for row in rows)
