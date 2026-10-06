"""Desktop kit regression: perceptible motion and sufficient bitmap detail."""
import io, json, os
from pathlib import Path
from urllib.parse import urlparse
from PIL import Image
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'work/refinement-1.9.13'
OUT.mkdir(parents=True, exist_ok=True)
PHASE = os.getenv('JOYRENT_TEST_PHASE', 'green')
rows = []
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width, density in [(1440, 1), (1920, 1), (3000, 1), (1920, 2)]:
        context = browser.new_context(viewport={'width': width, 'height': 1080},
                                      device_scale_factor=density, reduced_motion='no-preference')
        context.route('**/*', lambda r: r.continue_() if r.request.method in ['GET', 'HEAD']
                      and urlparse(r.request.url).hostname in ['localhost', '127.0.0.1'] else r.abort())
        page = context.new_page()
        page.goto('http://localhost:8080', wait_until='networkidle')
        expect(page.locator('.game-picker-button')).to_be_enabled()
        stage = page.locator('.kit-product-stage')
        stage.scroll_into_view_if_needed()
        expect(stage).to_have_attribute('data-motion', 'running')
        stage.evaluate('e=>Promise.all([...e.querySelectorAll("img")].map(i=>i.decode()))')
        # Sample the real rendered positions at the bottom and top of a cycle.
        # Regression: shortening the loop without increasing movement must fail.
        samples = stage.locator('.kit-element-motion').evaluate_all('''es => es.map(e => {
            const animation = e.getAnimations()[0];
            const timing = animation.effect.getTiming();
            animation.pause();
            animation.currentTime = timing.delay + timing.duration;
            const start = e.getBoundingClientRect().y;
            animation.currentTime = timing.delay + timing.duration * 1.5;
            const peak = e.getBoundingClientRect().y;
            animation.play();
            return {object: e.parentElement.className, travel: Math.abs(peak-start), duration: timing.duration};
        })''')
        images = []
        for img in stage.locator('.kit-game-box, .kit-controller-image').all():
            url = img.evaluate('e=>e.currentSrc')
            bitmap = Image.open(io.BytesIO(context.request.get(url).body()))
            rendered = img.bounding_box()['width']
            images.append({'file': url.rsplit('/', 1)[-1], 'pixels': bitmap.width,
                           'rendered': rendered, 'ratio': bitmap.width / rendered})
        problems = []
        if any(s['travel'] < 4 or s['duration'] > 10000 for s in samples):
            problems.append('Desktop movement is too small or slow to be perceptible')
        if any(i['ratio'] < 1.9 for i in images):
            problems.append('Desktop product bitmap lacks a 2x detail reserve')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        rows.append({'width': width, 'density': density, 'motion': samples, 'images': images,
                     'problems': problems, 'pass': not problems})
        context.close()
    browser.close()
(OUT / f'{PHASE}-desktop-detail.json').write_text(json.dumps(rows, indent=2)+'\n')
for row in rows:
    print(row['width'], row['density'], 'PASS' if row['pass'] else row['problems'])
assert all(row['pass'] for row in rows), 'Desktop detail/motion regression'
