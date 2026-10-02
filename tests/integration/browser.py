"""Browser checks against the real locally installed WordPress theme."""
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
from datetime import datetime,timedelta
from zoneinfo import ZoneInfo
import json
BASE='http://localhost:8080'
OUT=Path('docs/images');OUT.mkdir(parents=True,exist_ok=True)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':1440,'height':1000},reduced_motion='reduce',locale='uk-UA')
 errors=[];bad=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 page.on('response',lambda r:bad.append((r.status,r.url)) if r.status>=400 else None)
 page.goto(BASE);page.wait_for_selector('.hero');page.evaluate('document.fonts.ready')
 expect(page.locator('html')).to_have_attribute('lang','uk')
 assert page.evaluate('!!window.JOYRENT')
 assert page.evaluate('document.fonts.check(\'600 40px "Unbounded Variable"\')')
 for width in [320,360,390,430,768,1003,1280,1440,1920,2560,3355]:
  page.set_viewport_size({'width':width,'height':1000})
  page.evaluate('() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))')
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),f'Overflow at {width}'
  print('PASS: viewport',width)
 page.set_viewport_size({'width':1440,'height':1000})
 # One-day selection safely changes to 3 days on PS4.
 page.locator('#rates .tariff').first.get_by_role('button',name='Обрати',exact=True).click()
 expect(page.locator('.summary-price')).to_contain_text('1 день')
 page.locator('#rates').get_by_role('button',name='PS4',exact=True).click()
 expect(page.locator('.summary-price')).to_contain_text('3 дні')
 expect(page.locator('.summary-price strong')).to_have_text('750')
 date=(datetime.now(ZoneInfo('Europe/Kyiv'))+timedelta(days=2)).date()
 page.get_by_label('Дата отримання',exact=True).fill(date.isoformat())
 expect(page.get_by_label('Дата повернення',exact=True)).to_have_value((date+timedelta(days=3)).isoformat())
 page.locator('#rates').get_by_role('button',name='PS5',exact=True).click()
 page.locator('#games').get_by_role('button',name='На двох',exact=True).click()
 assert page.locator('.game-card').count()>0
 opener=page.get_by_role('button',name='Детальніше про It Takes Two',exact=True)
 opener.click();expect(page.get_by_role('dialog',name='It Takes Two')).to_be_visible()
 page.keyboard.press('Escape');expect(opener).to_be_focused()
 opener.click();page.get_by_role('button',name='Додати до заявки',exact=True).click()
 expect(page.locator('.selected-game')).to_contain_text('It Takes Two')
 page.locator('#games').get_by_role('button',name='Усі',exact=True).click()
 page.get_by_role('button',name='Наступні ігри',exact=True).click()
 assert page.locator('.game-track').evaluate('(e)=>e.scrollLeft')>0
 # Actual request, no success before required consent.
 page.get_by_role('button',name='Продовжити',exact=True).click()
 expect(page.locator('input[name=name]')).to_be_focused()
 page.locator('input[name=name]').fill('Тест JOYRENT')
 page.locator('input[name=phone]').fill('+380000000001')
 page.locator('input[name=address]').fill('Тестове місто, тестова адреса 1')
 assert not page.locator('form.booking-layout').evaluate('(e)=>e.checkValidity()')
 page.locator('.consent button').first.click()
 expect(page.get_by_role('dialog',name='Умови оренди')).to_be_visible()
 page.keyboard.press('Escape');expect(page.locator('.consent button').first).to_be_focused()
 page.locator('.consent input').check()
 with page.expect_response(lambda r:'/joyrent/v1/requests' in r.url) as sent:
  page.get_by_role('button',name='Надіслати заявку',exact=True).click()
 assert sent.value.status==201, sent.value.json()
 expect(page.locator('.booking-success')).to_be_visible()
 recap=page.locator('.success-recap').inner_text()
 page.locator('#rates').get_by_role('button',name='PS4',exact=True).click()
 assert page.locator('.success-recap').inner_text()==recap
 page.get_by_role('link',name='Обрати PS5',exact=True).click()
 expect(page.locator('#rates').get_by_role('button',name='PS5',exact=True)).to_have_attribute('aria-pressed','true')
 print('PASS: dates, console safety, game filter/carousel/dialog/focus, consent, real request and immutable confirmation')
 page.goto(BASE);page.wait_for_selector('.hero');page.evaluate('document.fonts.ready')
 page.set_viewport_size({'width':1440,'height':1000});page.screenshot(path=str(OUT/'desktop.png'))
 # Scroll through the entire page to load lazy photos and exercise reduced motion.
 for offset in range(0,9000,700):page.evaluate('(y)=>{window.scrollTo(0,y);}',offset);page.wait_for_timeout(70)
 assert page.locator('.controller-photo').evaluate('(e)=>e.getBoundingClientRect().width')>0
 assert page.evaluate('[...document.images].filter(i=>i.complete&&!i.naturalWidth).length')==0
 page.evaluate('()=>{window.scrollTo(0,0);}');page.screenshot(path=str(OUT/'desktop-full.png'),full_page=True)
 page.set_viewport_size({'width':390,'height':930});page.evaluate('()=>{window.scrollTo(0,0);}');page.screenshot(path=str(OUT/'mobile-hero.png'))
 page.get_by_role('button',name='Відкрити меню',exact=True).click()
 expect(page.locator('.mobile-nav')).to_be_visible()
 page.keyboard.press('Escape')
 expect(page.locator('.mobile-nav')).to_have_count(0)
 expect(page.get_by_role('button',name='Відкрити меню',exact=True)).to_be_focused()
 page.get_by_role('button',name='Відкрити меню',exact=True).click()
 page.locator('.mobile-nav').get_by_role('link',name='Ігри',exact=False).click()
 expect(page.locator('.mobile-nav')).to_have_count(0)
 page.locator('#games').screenshot(path=str(OUT/'mobile-games.png'))
 page.locator('#booking').scroll_into_view_if_needed()
 expect(page.locator('.mobile-rental-bar')).to_have_count(0)
 page.locator('#booking').screenshot(path=str(OUT/'mobile-booking.png'))
 page.set_viewport_size({'width':1003,'height':1568});page.evaluate('()=>{window.scrollTo(0,0);}');page.screenshot(path=str(OUT/'desktop-comparison.png'))
 assert not errors,errors
 assert not bad,bad
 print('PASS: mobile menu, loaded imagery, reduced motion, zero JavaScript errors and zero failed HTTP assets')
 browser.close()
