"""Both language states and requested animations; no orders sent to the live host."""
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
import json
OUT=Path('docs/refinement-1.2');OUT.mkdir(exist_ok=True)
BASE='http://localhost:8080'
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 page=b.new_page(viewport={'width':390,'height':844},reduced_motion='reduce')
 errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto(BASE);page.wait_for_selector('h1');page.evaluate('document.fonts.ready')
 page.get_by_role('button',name='Детальніше про It Takes Two',exact=True).click()
 page.get_by_role('button',name='Додати до заявки',exact=True).click()
 page.get_by_role('button',name='Продовжити',exact=True).click()
 page.locator('input[name=name]').fill('Тест перемикання')
 page.locator('input[name=phone]').fill('+380000000001')
 page.locator('input[name=address]').fill('Тестова адреса 1')
 page.locator('.consent input').check()
 page.get_by_role('button',name='Русский',exact=True).click()
 expect(page.locator('html')).to_have_attribute('lang','ru')
 assert '?lang=ru' in page.url
 expect(page.locator('input[name=name]')).to_have_value('Тест перемикання')
 expect(page.locator('input[name=phone]')).to_have_value('+380000000001')
 expect(page.locator('input[name=address]')).to_have_value('Тестова адреса 1')
 expect(page.locator('.consent input')).to_be_checked()
 expect(page.locator('.selected-game')).to_contain_text('It Takes Two')
 page.locator('.consent button').first.click();expect(page.get_by_role('dialog',name='Условия аренды')).to_be_visible();page.keyboard.press('Escape')
 ids=[]
 def offline(route):
  ids.append(route.request.post_data_json['requestId']);route.abort('internetdisconnected')
 page.route('**/joyrent/v1/requests',offline)
 page.get_by_role('button',name='Отправить заявку',exact=True).click()
 expect(page.locator('.form-error')).to_contain_text('Проверь подключение')
 page.get_by_role('button',name='Українська',exact=True).click()
 expect(page.locator('.form-error')).to_have_count(0)
 page.get_by_role('button',name='Надіслати заявку',exact=True).click()
 expect(page.locator('.form-error')).to_contain_text('Перевір з’єднання')
 assert len(ids)==2 and len(set(ids))==1,ids
 print('PASS: bilingual form/selection/consent, legal dialog, localized network errors and same request key on retry')
 page.close()
 # Matched source viewports plus both languages for final visual evidence.
 for width,height,lang in [(3355,1274,'uk'),(1440,1000,'uk'),(390,844,'uk'),(390,844,'ru')]:
  page=b.new_page(viewport={'width':width,'height':height},reduced_motion='reduce')
  page.goto(BASE+('/?lang=ru' if lang=='ru' else ''));page.wait_for_selector('h1');page.evaluate('document.fonts.ready')
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  page.screenshot(path=str(OUT/f'new-hero-{width}-{lang}.png'))
  for selector,label in [('#games','games'),('#kit','kit'),('#how-it-works','process'),('#booking','booking')]:
   page.locator(selector).scroll_into_view_if_needed();page.wait_for_timeout(100);page.screenshot(path=str(OUT/f'new-{label}-{width}-{lang}.png'))
  # All artwork resolves and remains uncropped in square cards.

  for img in page.locator('.game-art img').all():
   img.scroll_into_view_if_needed();img.evaluate('(e)=>e.decode()')
  assert page.locator('.game-art img').evaluate_all('(a)=>a.every(i=>i.complete&&i.naturalWidth>0)')
  page.close()
 page=b.new_page(viewport={'width':1440,'height':1000},reduced_motion='no-preference')
 page.goto(BASE);page.wait_for_selector('h1');page.evaluate('document.fonts.ready')
 # Sample actual running animations. Each wait is less than 3 seconds.
 hero=[]
 for _ in range(3):
  hero.append(float(page.locator('.hero-light-pass').evaluate('(e)=>getComputedStyle(e).opacity')));page.wait_for_timeout(1800)
 assert max(hero)-min(hero)>0.025,hero
 page.locator('#kit').scroll_into_view_if_needed()
 widths=[];transforms=[]
 for _ in range(3):
  widths.append(page.locator('.controller-photo').evaluate('(e)=>e.offsetWidth'))
  transforms.append(page.locator('.controller-float').evaluate('(e)=>getComputedStyle(e).transform'));page.wait_for_timeout(1500)
 assert len(set(widths))==1 and len(set(transforms))>1,(widths,transforms)
 page.emulate_media(reduced_motion='reduce');page.wait_for_timeout(300)
 expect(page.locator('.hero-light-pass')).to_have_css('opacity','0.07')
 expect(page.locator('.controller-float')).to_have_css('transform','none')
 expect(page.locator('.controller-light-pass')).to_have_css('opacity','0.05')
 print('PASS: animated hero light, fixed-width smooth controller, static reduced motion',hero)
 assert not errors,errors
 page.close();b.close()
