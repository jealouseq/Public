"""Game and legal dialogs stay centered; cover frames match the source artwork."""
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

OUT = Path('work/dialog-regression')
OUT.mkdir(parents=True,exist_ok=True)
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    for width, height in [(1440, 1000), (3355, 1274), (768, 1024), (390, 844), (320, 568), (844, 390)]:
        page = browser.new_page(viewport={'width': width, 'height': height}, reduced_motion='reduce')
        errors = []
        page.on('pageerror', lambda e: errors.append(str(e)))
        page.goto('http://localhost:8080')
        page.wait_for_selector('h1')
        page.evaluate('document.fonts.ready')
        opener = page.get_by_role('button', name='Детальніше про Sackboy: A Big Adventure', exact=True)
        source_width = opener.evaluate("(e)=>e.clientWidth")
        opener.click()
        dialog = page.get_by_role('dialog', name='Sackboy: A Big Adventure')
        expect(dialog).to_be_visible()
        box = dialog.bounding_box()
        assert abs(box['x'] + box['width'] / 2 - width / 2) < 2, (width, box)
        assert abs(box['y'] + box['height'] / 2 - height / 2) < 2, (height, box)
        assert box['x'] >= 12 and box['y'] >= 12, box
        assert box['height'] <= height - 24, box
        image = page.locator('.dialog-cover')
        image.evaluate('(e)=>e.decode()')
        rendered_width = image.evaluate('(e)=>e.clientWidth')
        assert abs(rendered_width-source_width) < 1.5, (source_width,rendered_width)
        ratios = image.evaluate('(e)=>[e.clientWidth/e.clientHeight,e.naturalWidth/e.naturalHeight]')
        assert abs(ratios[0] - ratios[1]) < .015, ratios
        assert dialog.evaluate('(e)=>e.scrollWidth<=e.clientWidth'), 'Dialog horizontal overflow'
        # Close/add controls remain reachable even on short landscape screens.
        page.locator('.dialog-copy .button').scroll_into_view_if_needed()
        expect(page.locator('.dialog-copy .button')).to_be_in_viewport()
        page.locator('.dialog-close').scroll_into_view_if_needed()
        expect(page.locator('.dialog-close')).to_be_in_viewport()
        page.screenshot(path=str(OUT / f'dialog-{width}.png'))
        page.keyboard.press('Escape')
        expect(opener).to_be_focused()
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        page.get_by_role('button',name='Продовжити',exact=True).click()
        page.locator('.consent button').first.click()
        legal = page.get_by_role('dialog', name='Умови оренди')
        lb = legal.bounding_box()
        assert abs(lb['x'] + lb['width'] / 2 - width / 2) < 2
        assert abs(lb['y'] + lb['height'] / 2 - height / 2) < 2
        page.keyboard.press('Escape')
        assert not errors, errors
        page.close()
    page = browser.new_page(viewport={'width':700,'height':1000}, reduced_motion='reduce')
    page.goto('http://localhost:8080');page.wait_for_selector('h1')
    page.get_by_role('button',name='Детальніше про Sackboy: A Big Adventure',exact=True).click()
    for width in [701,768,390,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        page.wait_for_timeout(100)
        copy_width=page.locator('.dialog-copy').evaluate('(e)=>e.clientWidth-parseFloat(getComputedStyle(e).paddingLeft)-parseFloat(getComputedStyle(e).paddingRight)')
        assert copy_width>=250,(width,copy_width)
        assert page.locator('dialog').evaluate('(e)=>e.scrollWidth<=e.clientWidth')
    page.close()
    browser.close()
print('PASS: centered native game/legal dialogs, full-ratio covers, short-screen controls, focus return, no overflow')
