"""Verify the installed theme without transmitting bookings or changing store data."""
import copy, json, math, os, re, urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[2]
BASE = os.getenv('JOYRENT_TEST_URL', 'http://localhost:8080').rstrip('/')
OUT = ROOT / 'docs/refinement-1.9.6'
(OUT / 'screens').mkdir(parents=True, exist_ok=True)
with urllib.request.urlopen(BASE + '/wp-json/joyrent/v1/catalog') as response:
    catalog = json.load(response)
rows = []

def number(text):
    return float(re.sub(r'[^0-9,.]', '', text).replace(',', '.'))

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])

    def open_page(lang, width, *, custom=None, reduced='reduce', height=1000):
        context = browser.new_context(viewport={'width': width, 'height': height},
                                      reduced_motion=reduced, is_mobile=width <= 480, has_touch=width <= 480)
        writes, errors = [], []
        def route(request):
            if request.request.method not in ('GET', 'HEAD'):
                writes.append(request.request.url)
                request.abort()
            elif custom and request.request.url.endswith('/joyrent/v1/catalog'):
                request.fulfill(status=200, content_type='application/json', body=json.dumps(custom))
            else:
                request.continue_()
        context.route('**/*', route)
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(BASE + ('/?lang=ru' if lang == 'ru' else '/'), wait_until='networkidle')
        page.evaluate('document.fonts.ready')
        expect(page.locator('[data-intent="continue"]')).to_be_enabled()
        return context, page, writes, errors

    for lang in ['uk', 'ru']:
        for width in [320, 390, 480, 481, 700, 768, 1024, 1440]:
            context, page, writes, errors = open_page(lang, width)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            assert page.locator('#faq, .faq-section').count() == 0
            expect(page.locator('.hero-highlights li')).to_have_count(3)
            hero_button = page.locator('.hero-primary').bounding_box()
            if width <= 700:
                assert 44 <= hero_button['height'] <= 60 and hero_button['width'] <= 240, hero_button
            choices = 0
            for console in ['ps5', 'ps4']:
                page.locator('#rates').get_by_role('button', name=console.upper(), exact=True).click()
                expect(page.locator('.tariff')).to_have_count(len(catalog['tariffs'][console]))
                for index, tariff in enumerate(catalog['tariffs'][console]):
                    card = page.locator('.tariff').nth(index)
                    assert number(card.locator('.tariff-price strong').inner_text()) == tariff['price']
                    exact = tariff['price'] / tariff['days']
                    shown = math.floor(exact * 100 + .5) / 100
                    line = card.locator('.daily-price').inner_text()
                    assert number(line) == shown, (tariff, line)
                    assert ('≈' in line) == (abs(shown - exact) > 1e-8)
                    card.locator('.tariff-link').click()
                    assert number(page.locator('.summary-price strong').inner_text()) == tariff['price']
                    assert number(page.locator('.summary-daily').inner_text()) == shown
                    assert page.locator(f'[data-intent="continue"]').is_visible()
                    choices += 1
            # Native radio keys select the same method as a pointer, without changing price.
            previous_total = page.locator('.summary-subtotal strong').inner_text()
            page.locator('.security-choice').nth(1).click()
            expect(page.locator('input[value="contract"]')).to_be_checked()
            expect(page.locator('.summary-security')).to_contain_text('договор')
            assert page.locator('.summary-subtotal strong').inner_text() == previous_total
            page.locator('input[value="contract"]').focus()
            page.keyboard.press('ArrowLeft')
            expect(page.locator('input[value="deposit"]')).to_be_checked()
            tiles = page.locator('.security-choice').all()
            bounds = [tile.bounding_box() for tile in tiles]
            choice_grid = page.locator('.security-choices').evaluate('e => ({width:e.clientWidth,gap:parseFloat(getComputedStyle(e).columnGap)})')
            if width <= 480 or choice_grid['width'] < 480 + choice_grid['gap']:
                assert abs(bounds[0]['x'] - bounds[1]['x']) < 1 and bounds[1]['y'] > bounds[0]['y']
            else:
                assert abs(bounds[0]['y'] - bounds[1]['y']) < 1
            for tile in tiles:
                assert tile.evaluate('e => e.scrollWidth <= e.clientWidth'), (lang, width, tile.inner_text())
            for selector in ['.security-detail-value', '.security-help', '.summary-note', '.booking-reassurance p > span']:
                for text in page.locator(selector).all():
                    assert text.evaluate('e => parseFloat(getComputedStyle(e).fontSize) >= 14')
            assert page.locator('.booking-reassurance').inner_text().startswith('Без оплати' if lang == 'uk' else 'Без оплаты')
            if width in [390, 1440]:
                for selector, label in [('#top', 'hero'), ('#rates', 'rates'), ('.security-field', 'security'), ('.booking-summary', 'summary'), ('#kit', 'kit')]:
                    element = page.locator(selector)
                    element.evaluate('e => window.scrollTo({top:e.getBoundingClientRect().top+scrollY-24,behavior:"instant"})')
                    page.wait_for_timeout(100)
                    page.screenshot(path=str(OUT / 'screens' / f'{label}-{lang}-{width}.png'))
            page.locator('[data-intent="continue"]').click()
            expect(page.locator('[name="name"]')).to_be_focused()
            expect(page.locator('.booking-reassurance')).to_be_visible()
            # A street suggestion is an address choice, never a submission or a legal-dialog opener.
            address = page.locator('[name="address"]')
            address.fill('Фонта')
            option = page.get_by_role('option').first
            expect(option).to_be_visible()
            if width <= 480:
                option.tap()
            else:
                option.click()
            assert 'фонтан' in address.input_value().lower(), address.input_value()
            assert page.locator('dialog[open]').count() == 0
            opener = page.locator('.consent button').first
            opener.click()
            expect(page.locator('.legal-dialog')).to_be_visible()
            page.keyboard.press('Escape')
            expect(opener).to_be_focused()
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
            assert not writes and not errors, (writes, errors)
            rows.append({'language': lang, 'width': width, 'tariffChoices': choices,
                         'streetSuggestion': True, 'legalFocusReturn': True,
                         'overflow': False, 'networkWrites': len(writes), 'errors': errors})
            context.close()

    # Owner-configured prices, deposit amounts and paid extra controllers remain authoritative.
    custom = copy.deepcopy(catalog)
    custom['tariffs']['ps5'][1]['price'] = 1555
    custom['settings'].update(depositPs5=12345, baseControllers=1, extraControllerFee=100)
    for lang in ['uk', 'ru']:
        context, page, writes, errors = open_page(lang, 390, custom=custom)
        expect(page.locator('.hero-highlights li')).to_have_count(2)
        assert 'без доплат' not in page.locator('.kit-note').inner_text().lower()
        page.locator('#rates .tariff-link').nth(1).click()
        assert number(page.locator('.summary-price strong').inner_text()) == 1555
        assert number(page.locator('.summary-daily').inner_text()) == 518.33
        assert number(page.locator('.security-choice').first.locator('.security-detail-value').first.inner_text()) == 12345
        page.locator('.controller-options button').nth(1).click()
        assert number(page.locator('.summary-subtotal strong').inner_text()) == 1655
        assert not writes and not errors
        rows.append({'case': 'configured-prices-and-controller-fee', 'language': lang, 'pass': True})
        context.close()

    # Real motion reaches its end state; changing the OS preference stops existing loops.
    context, page, writes, errors = open_page('uk', 1440, reduced='no-preference')
    heading = page.locator('#booking .section-heading')
    heading.scroll_into_view_if_needed()
    page.wait_for_timeout(600)
    assert float(heading.evaluate('e => getComputedStyle(e).opacity')) == 1
    page.emulate_media(reduced_motion='reduce')
    page.locator('.controller-stage').scroll_into_view_if_needed()
    page.wait_for_timeout(100)
    assert page.locator('.controller-stage').get_attribute('data-motion') == 'paused'
    assert page.locator('.controller-float').evaluate('e => getComputedStyle(e).animationName') == 'none'
    assert page.locator('.summary-button').first.evaluate('e => getComputedStyle(e).transitionProperty') == 'none'
    assert not writes and not errors
    rows.append({'case': 'motion-and-live-reduced-motion', 'pass': True})
    context.close()
    browser.close()

(OUT / 'browser-check.json').write_text(json.dumps(rows, ensure_ascii=False, indent=2) + '\n')
print(f'PASS: {len(rows)} cases, 112 tariff choices, both languages, no real bookings or network writes.')
