"""Local-only verification of console selection, pricing and motion preferences."""
import copy, json, os, traceback, urllib.request
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
BASE = os.getenv('JOYRENT_TEST_URL', 'http://localhost:8080').rstrip('/')
assert urlparse(BASE).hostname in ['localhost', '127.0.0.1'], 'Use a local WordPress site.'
PHASE = os.getenv('JOYRENT_TEST_PHASE', 'green')
OUT = ROOT / 'work/refinement-1.9.8'
OUT.mkdir(parents=True, exist_ok=True)
with urllib.request.urlopen(BASE + '/wp-json/joyrent/v1/catalog') as response:
    catalog = json.load(response)
rows = []

with sync_playwright() as pw:
    browser = pw.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    def case(lang, width, reduced='reduce', empty=False):
        context = browser.new_context(viewport={'width': width, 'height': 844 if width < 701 else 1000},
            reduced_motion=reduced, is_mobile=width < 701, has_touch=width < 701)
        errors, writes = [], []
        custom = copy.deepcopy(catalog)
        if empty:
            custom['tariffs']['ps4'] = []
        def route(r):
            if r.request.method not in ['GET', 'HEAD']:
                writes.append(r.request.url); r.abort()
            elif urlparse(r.request.url).hostname not in ['localhost', '127.0.0.1']:
                r.abort()
            elif empty and r.request.url.endswith('/joyrent/v1/catalog'):
                r.fulfill(status=200, content_type='application/json', body=json.dumps(custom))
            else:
                r.continue_()
        context.route('**/*', route)
        page = context.new_page()
        page.set_default_timeout(5000)
        page.on('pageerror', lambda error: errors.append(str(error)))
        try:
            page.goto(BASE + ('/?lang=ru' if lang=='ru' else '/'), wait_until='networkidle')
            expect(page.locator('.game-picker-button')).to_be_enabled()
            feature = page.locator('.console-showcase')
            expect(feature).to_be_visible()
            assert page.locator('.hero-benefits').count()==0, 'Removed hero strip still present'
            feature.scroll_into_view_if_needed()
            panel = feature.locator('.console-feature')
            expect(panel).to_have_attribute('data-console', 'ps5')
            expect(panel).to_contain_text('EA Play')
            expect(panel).to_contain_text('PS Plus Deluxe')
            # The slider changes the actual rental selection, not just its photograph.
            feature.locator('.console-showcase-next').click()
            expect(panel).to_have_attribute('data-console', 'ps4')
            expect(page.locator('#booking .console-switch button').last).to_have_attribute('aria-pressed', 'true')
            expect(panel).to_contain_text('EA Play')
            expect(panel).to_contain_text('PS Plus Deluxe')
            if empty:
                expect(panel.locator('.console-book')).to_be_disabled()
                assert not panel.locator('.console-rate').count()
            else:
                rate = custom['tariffs']['ps4'][0]
                panel.locator('.console-book').focus()
                page.keyboard.press('Enter')
                expect(page.locator('#booking .term-options button').filter(has_text=str(rate['days'])).first).to_have_attribute('aria-pressed', 'true')
                # A corresponding real tariff remains selectable and the amount is unchanged.
                assert str(rate['price']) in ''.join(page.locator('#booking .summary-price').inner_text().split())
            feature.locator('.console-showcase-previous').click()
            expect(panel).to_have_attribute('data-console', 'ps5')
            process = page.locator('.rental-steps')
            process.scroll_into_view_if_needed()
            cards = process.locator('li')
            expect(cards).to_have_count(4)
            assert cards.locator('h3').count()==4
            assert '25000' not in process.inner_text().replace(' ', ''), 'Process hard-codes a deposit amount'
            if reduced=='reduce':
                assert page.evaluate('''() => [...document.querySelectorAll('.rental-steps *, .console-showcase *')].every(e => getComputedStyle(e).animationName === 'none')''')
            for target in [feature, process]:
                assert target.evaluate('e=>e.scrollWidth<=e.clientWidth+1'), 'Section overflow'
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'), 'Page overflow'
            assert not page.locator('main .faq-section').count(), 'FAQ moved onto the home page'
            assert not errors and not writes, (errors, writes)
            rows.append({'language':lang,'width':width,'motion':reduced,'emptyPs4':empty,'pass':True})
        except Exception:
            rows.append({'language':lang,'width':width,'motion':reduced,'emptyPs4':empty,'pass':False,'error':traceback.format_exc()})
        finally:
            context.close()

    def motion_case(width):
        context = browser.new_context(viewport={'width':width,'height':844 if width<701 else 1000},
            reduced_motion='no-preference',is_mobile=width<701,has_touch=width<701)
        context.route('**/*',lambda r:r.continue_() if urlparse(r.request.url).hostname in ['localhost','127.0.0.1'] and r.request.method in ['GET','HEAD'] else r.abort())
        page=context.new_page()
        try:
            page.goto(BASE,wait_until='networkidle')
            expect(page.locator('.game-picker-button')).to_be_enabled()
            feature=page.locator('.console-showcase')
            feature.scroll_into_view_if_needed()
            expect(feature).to_have_attribute('data-motion','running')
            page.locator('#rates .console-switch button').last.click()
            exiting=feature.locator('.console-feature[data-console="ps5"]')
            if exiting.count():
                assert exiting.locator('.console-book').is_disabled(), 'Exiting PS5 card can select its stale tariff for PS4'
            old_tariff=page.locator('#rates .tariff-link[aria-label^="Обрати PS5"]')
            if old_tariff.count():
                assert old_tariff.first.is_disabled(), 'Exiting PS5 tariff grid remains interactive for PS4'
            expect(feature.locator('.console-feature')).to_have_attribute('data-console','ps4')
            page.emulate_media(reduced_motion='reduce')
            expect(feature).to_have_attribute('data-motion','paused')
            assert feature.locator('.console-photo-float').evaluate('e=>getComputedStyle(e).animationName')=='none'
            page.emulate_media(reduced_motion='no-preference')
            expect(feature).to_have_attribute('data-motion','running')
            page.locator('#booking').scroll_into_view_if_needed()
            expect(feature).to_have_attribute('data-motion','paused')
            process=page.locator('.rental-process')
            for card in process.locator('.rental-step-card').all():
                card.scroll_into_view_if_needed()
                expect(card).to_have_css('opacity', '1')
            if width>=701:
                card=process.locator('.rental-step-surface').first
                card.hover()
                page.wait_for_function("new DOMMatrix(getComputedStyle(document.querySelector('.rental-step-surface')).transform).m42 < -5")
            rows.append({'case':'motion-and-rapid-switch','width':width,'pass':True})
        except Exception:
            rows.append({'case':'motion-and-rapid-switch','width':width,'pass':False,'error':traceback.format_exc()})
        finally:
            context.close()

    if PHASE=='red':
        case('uk',390)
    elif PHASE=='red-motion':
        motion_case(1440)
    else:
        for lang in ['uk','ru']:
            for width in [320,390,768,1024,1440]: case(lang,width)
            for width in [390,1440]: case(lang,width,'no-preference')
            for width in [390,1440]: case(lang,width,empty=True)
        for width in [390,1440]: motion_case(width)
    browser.close()

(OUT / f'{PHASE}-showcase-check.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2)+'\n')
for row in rows:
    print(row.get('language','uk'),row['width'],row.get('motion','motion check'),'empty' if row.get('emptyPs4') else 'catalog','PASS' if row['pass'] else row['error'])
assert all(r['pass'] for r in rows)
