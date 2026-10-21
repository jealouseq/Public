from playwright.sync_api import sync_playwright,expect
from pathlib import Path
import json
OUT=Path('docs/refinement-1.5');OUT.mkdir(parents=True,exist_ok=True)
results=[]
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for lang in ['uk','ru']:
  for width in [320,360,375,390,430,700,768,900,901,980,1024,1280,1440,1920,2560,3355]:
   page=b.new_page(viewport={'width':width,'height':1700},reduced_motion='reduce')
   errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
   page.goto('http://localhost:8080'+('/?lang=ru' if lang=='ru' else ''));page.wait_for_selector('h1');page.evaluate('document.fonts.ready')
   assert page.locator('.final-cta').count()==0
   assert page.locator('.tariff-note').count()==0
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),('overflow',lang,width)
   heading=page.locator('h1').evaluate('(e)=>({height:e.clientHeight,lineHeight:parseFloat(getComputedStyle(e).lineHeight),baselines:[...new Set([...e.querySelectorAll(".hero-letter")].map(a=>Math.round(a.getBoundingClientRect().y)))],width:e.clientWidth,scroll:e.scrollWidth})')
   assert len(heading['baselines'])==2 and heading['scroll']<=heading['width']+1,(lang,width,heading)
   for console,count in [('PS5',4),('PS4',3)]:
    page.locator('#rates').get_by_role('button',name=console,exact=True).click();expect(page.locator('.tariff')).to_have_count(count)
    assert page.locator('.tariff-benefit').evaluate_all('(e)=>e.filter(x=>x.textContent.trim()).length')==2
    for card in page.locator('.tariff').all():
     button=card.locator('.tariff-link');box=button.bounding_box();assert box['height']>=44
     card_bounds=card.bounding_box();assert box['x']>=card_bounds['x']-1 and box['x']+box['width']<=card_bounds['x']+card_bounds['width']+1
     button.click();assert page.locator('.summary-price strong').inner_text()==card.locator('.tariff-price strong').inner_text()
    if width<=700:
     switch=page.locator('#rates .console-switch').bounding_box();assert abs(switch['x']+switch['width']/2-width/2)<1.5,(lang,width,switch)
    if width in [320,390,1440] and lang=='uk':page.locator('#rates').screenshot(path=str(OUT/f'rates-{console.lower()}-{width}-{lang}.png'))
   page.locator('#rates').get_by_role('button',name='PS5',exact=True).click()
   delivery=page.locator('#delivery');delivery.scroll_into_view_if_needed()
   assert delivery.evaluate('(e)=>e.scrollWidth<=e.clientWidth')
   if width in [320,390,1440] or width==390 and lang=='ru':
    for selector,label in [('#top','hero'),('#how-it-works','delivery'),('#games','games'),('#booking','booking')]:
     page.locator(selector).scroll_into_view_if_needed();page.wait_for_timeout(80);page.locator(selector).screenshot(path=str(OUT/f'{label}-{width}-{lang}.png'))
    page.locator('.site-footer').screenshot(path=str(OUT/f'footer-{width}-{lang}.png'))
   assert not errors,errors
   results.append({'language':lang,'width':width,'headingLines':2,'tariffChoices':7,'errors':errors,'overflow':False})
   page.close()
 b.close()
OUT.joinpath('layout-results.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
print('PASS: 32 UA/RU layouts, 224 tariff choices, two-line heading, delivery bounds, no final CTA or overflow')
