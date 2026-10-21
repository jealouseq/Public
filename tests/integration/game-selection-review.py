"""Selection review and catalog boundaries, with local-only read access."""
import copy, json, os, traceback, urllib.request
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
BASE = os.getenv('JOYRENT_TEST_URL', 'http://localhost:8080').rstrip('/')
assert urlparse(BASE).hostname in ['localhost', '127.0.0.1'], 'This check must use a local site.'
PHASE = os.getenv('JOYRENT_TEST_PHASE', 'green')
OUT = ROOT / 'work/refinement-1.9.7'
OUT.mkdir(parents=True, exist_ok=True)
rows = []
with urllib.request.urlopen(BASE + '/wp-json/joyrent/v1/catalog') as response:
    catalog = json.load(response)

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    def open_page(lang='uk', width=390, custom=None):
        context = browser.new_context(viewport={'width': width, 'height': 844 if width <= 700 else 1000},
                                      reduced_motion='reduce', is_mobile=width <= 700, has_touch=width <= 700)
        writes, errors = [], []
        def route(r):
            if r.request.method not in ['GET', 'HEAD']:
                writes.append(r.request.url)
                r.abort()
            elif urlparse(r.request.url).hostname not in ['localhost', '127.0.0.1']:
                r.abort()
            elif custom and r.request.url.endswith('/joyrent/v1/catalog'):
                r.fulfill(status=200, content_type='application/json', body=json.dumps(custom))
            else:
                r.continue_()
        context.route('**/*', route)
        page = context.new_page()
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.goto(BASE + ('/?lang=ru' if lang == 'ru' else '/'), wait_until='networkidle')
        page.evaluate('document.fonts.ready')
        expect(page.locator('.game-picker-button')).to_be_enabled()
        return context, page, writes, errors

    def boundaries(lang='uk', width=390):
        context, page, writes, errors = open_page(lang, width)
        try:
            track = page.locator('#games .game-track')
            track.scroll_into_view_if_needed()
            previous, following = page.locator('#games .carousel-controls button').all()
            expect(previous).to_be_disabled()
            expect(following).to_be_enabled()
            assert 'PS5' in page.locator('#games .eyebrow').inner_text()
            following.click()
            expect(previous).to_be_enabled()
            track.evaluate('e=>e.scrollTo({left:e.scrollWidth,behavior:"instant"})')
            expect(following).to_be_disabled()
            previous.click()
            expect(following).to_be_enabled()
            page.locator('#rates').get_by_role('button', name='PS4', exact=True).click()
            expect(previous).to_be_disabled()
            assert 'PS4' in page.locator('#games .eyebrow').inner_text()
            assert not writes and not errors, (writes, errors)
        finally:
            context.close()

    def selection(lang='uk', width=390):
        context, page, writes, errors = open_page(lang, width)
        try:
            opener = page.locator('.game-picker-button')
            opener.click()
            picker = page.locator('.game-picker-dialog')
            cards = picker.locator('.picker-game')
            titles = [cards.nth(i).locator('h3').inner_text() for i in [0, 3]]
            cards.nth(0).locator('.picker-game-toggle').click()
            cards.nth(3).locator('.picker-game-toggle').click()
            search = picker.locator('input[type="search"]')
            search.fill('A game outside this catalog')
            expect(cards).to_have_count(0)
            review = picker.locator('.game-picker-selection-toggle')
            expect(review).to_be_enabled()
            review.click()
            expect(review).to_have_attribute('aria-pressed', 'true')
            expect(cards).to_have_count(2)
            assert cards.locator('h3').all_text_contents() == titles, (cards.locator('h3').all_text_contents(), titles)
            expect(search).to_have_value('')
            review.click()
            expect(search).to_have_value('A game outside this catalog')
            expect(cards).to_have_count(0)
            review.click()
            cards.first.locator('.game-art').click()
            detail = page.locator('.game-dialog')
            expect(detail).to_be_visible()
            detail.locator('.button').focus()
            page.keyboard.press('Tab')
            expect(detail.locator('.dialog-close')).to_be_focused()
            page.keyboard.press('Shift+Tab')
            expect(detail.locator('.button')).to_be_focused()
            detail.locator('.button').click()
            expect(detail).to_have_count(0)
            expect(cards).to_have_count(1)
            expect(review).to_be_focused()
            cards.first.locator('.picker-game-toggle').click()
            expect(cards).to_have_count(0)
            expect(review).to_be_focused()
            expect(picker.locator('.game-picker-empty')).to_be_visible()
            expect(picker.locator('.game-picker-empty button')).to_be_enabled()
            picker.locator('.game-picker-empty button').click()
            expect(picker.locator('.game-picker-scroll')).to_be_focused()
            expect(search).to_have_value('A game outside this catalog')
            # One wish path prefills the query, retains the text, and does not submit a booking.
            picker.locator('.missing-game-toggle').click()
            request = picker.locator('.missing-game-fields input')
            expect(request).to_have_value('A game outside this catalog')
            request.fill('Minecraft, якщо буде доступна')
            picker.locator('.missing-game-toggle').click()
            expect(picker.locator('.missing-game-saved')).to_contain_text('Minecraft')
            review.click()
            expect(picker.locator('.game-picker-request')).to_be_visible()
            expect(picker.locator('.game-picker-request')).to_contain_text('Minecraft')
            # Native modal focus stays inside after cycling through the controls.
            for _ in range(15):
                page.keyboard.press('Tab')
                assert page.evaluate('document.querySelector(".game-picker-dialog").contains(document.activeElement)'), page.evaluate('({tag:document.activeElement.tagName,className:document.activeElement.className})')
            picker.locator('.game-picker-footer .button').focus()
            page.keyboard.press('Tab')
            expect(picker.locator('.game-picker-heading button')).to_be_focused()
            page.keyboard.press('Shift+Tab')
            expect(picker.locator('.game-picker-footer .button')).to_be_focused()
            page.keyboard.press('Escape')
            expect(picker).to_have_count(0)
            expect(opener).to_be_focused()
            expect(page.locator('.requested-game-note')).to_contain_text('Minecraft')
            opener.click()
            expect(page.locator('.missing-game-fields input')).to_have_value('Minecraft, якщо буде доступна')
            page.keyboard.press('Escape')
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
            assert not writes and not errors, (writes, errors)
            page.screenshot(path=str(OUT / f'{PHASE}-booking-{lang}-{width}.png'))
        finally:
            context.close()

    cases = [(kind, fn, lang, width) for lang in ['uk', 'ru'] for width in [320, 390, 768, 1440]
             for kind, fn in [('carousel-boundaries', boundaries), ('selection-review', selection)]]
    def limited_catalog(lang, width):
        custom = copy.deepcopy(catalog)
        custom['games'] = custom['games'][:2]
        custom['settings']['maxGames'] = 1
        context, page, writes, errors = open_page(lang, width, custom)
        try:
            previous, following = page.locator('#games .carousel-controls button').all()
            expect(previous).to_be_disabled()
            expect(following).to_be_disabled()
            page.set_viewport_size({'width': 320, 'height': 740})
            expect(following).to_be_enabled()
            page.locator('.game-picker-button').click()
            picker = page.locator('.game-picker-dialog')
            cards = picker.locator('.picker-game')
            expect(cards).to_have_count(2)
            cards.first.locator('.picker-game-toggle').click()
            expect(cards.nth(1).locator('.picker-game-toggle')).to_be_disabled()
            picker.locator('.game-picker-selection-toggle').click()
            expect(cards).to_have_count(1)
            cards.first.locator('.picker-game-toggle').click()
            picker.locator('.game-picker-empty button').click()
            expect(cards.nth(1).locator('.picker-game-toggle')).to_be_enabled()
            cards.nth(1).locator('.picker-game-toggle').click()
            picker.locator('.game-picker-footer .button').click()
            expect(page.locator('.booking-games .selected-game')).to_have_count(1)
            assert not writes and not errors, (writes, errors)
        finally:
            context.close()

    def compact_keyboard(lang, width):
        context, page, writes, errors = open_page(lang, width)
        try:
            page.locator('.game-picker-button').click()
            picker = page.locator('.game-picker-dialog')
            picker.locator('input[type="search"]').fill('A wishlist game outside catalog')
            picker.locator('.missing-game-toggle').click()
            request = picker.locator('.missing-game-fields input')
            page.set_viewport_size({'width': width, 'height': 320})
            expect(picker).to_have_attribute('data-compact-viewport', 'true')
            expect(request).to_be_focused()
            page.wait_for_timeout(100)
            field, footer, done = [e.bounding_box() for e in [request, picker.locator('.game-picker-footer'), picker.locator('.game-picker-footer .button')]]
            assert field['y'] >= 16 and field['y'] + field['height'] <= footer['y'] + 1, (field, footer)
            assert done['y'] >= 0 and done['y'] + done['height'] <= 320, done
            request.fill('QA private wish ' + 'x' * 90)
            picker.locator('.game-picker-selection-toggle').click()
            expect(picker.locator('.game-picker-request')).to_be_visible()
            expect(request).to_have_value('QA private wish ' + 'x' * 90)
            assert 'QA private wish' not in page.evaluate('JSON.stringify(sessionStorage)')
            page.screenshot(path=str(OUT / f'{PHASE}-compact-{lang}.png'))
            picker.locator('.game-picker-footer .button').click()
            expect(page.locator('.requested-game-note')).to_contain_text('QA private wish')
            assert not writes and not errors, (writes, errors)
        finally:
            context.close()

    cases += [(kind, fn, lang, width) for lang in ['uk', 'ru']
              for kind, fn, width in [('catalog-limit-and-resize', limited_catalog, 1440), ('compact-keyboard', compact_keyboard, 390)]]
    if PHASE == 'red':
        cases = [(kind, fn, 'uk', 390) for kind, fn in [('carousel-boundaries', boundaries), ('selection-review', selection)]]
    for name, fn, lang, width in cases:
        try:
            fn(lang, width)
            rows.append({'case': name, 'language': lang, 'width': width, 'pass': True})
        except Exception as error:
            rows.append({'case': name, 'language': lang, 'width': width, 'pass': False, 'error': traceback.format_exc()})
    browser.close()

(OUT / f'{PHASE}-selection-check.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2) + '\n')
for row in rows:
    print(row['case'], row['language'], row['width'], 'PASS' if row['pass'] else 'FAIL: ' + row['error'].splitlines()[-1])
assert all(row['pass'] for row in rows), f'{sum(not row["pass"] for row in rows)} checks failed'
