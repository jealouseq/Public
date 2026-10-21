"""Network regression: decoration loads near its section without changing reserved size.
Use the source Vite URL or packaged preview, with no live booking writes.
"""
import asyncio, json, os
from pathlib import Path
from playwright.async_api import async_playwright

async def main():
    out=Path(os.environ.get('JOYRENT_EVIDENCE_DIR','work/verification-1.9.3/deferred-media'))
    out.mkdir(parents=True,exist_ok=True)
    result=[]
    async with async_playwright() as p:
        browser=await p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox'])
        for width,height in [(1440,900),(390,844)]:
            context=await browser.new_context(viewport={'width':width,'height':height},reduced_motion='reduce')
            page=await context.new_page(); seen=[]; errors=[]
            page.on('request',lambda r: seen.append(r.url))
            page.on('pageerror',lambda e: errors.append(str(e)))
            async def readonly(route):
                if route.request.method not in ('GET','HEAD'): await route.abort()
                else: await route.continue_()
            await page.route('**/*',readonly)
            await page.goto(os.environ.get('JOYRENT_TEST_URL','http://localhost:8080'),wait_until='networkidle')
            await page.wait_for_timeout(100)
            distant=lambda: [u for u in seen if any(n in u for n in ['dualsense-cutout','delivery-basemap-odessa.webp','delivery-zone-map-landscape.svg'])]
            assert not distant(),('decoration fetched at hero',width,distant())
            heights=await page.locator('.controller-float,.delivery-preview-map').evaluate_all('(els)=>els.map(e=>e.offsetHeight)')
            assert all(h>100 for h in heights),('unreserved decoration',width,heights)
            await page.locator('#kit').scroll_into_view_if_needed()
            await page.wait_for_function("document.querySelector('.controller-photo')?.complete && document.querySelector('.controller-photo')?.naturalWidth>0")
            await page.locator('.delivery-preview').scroll_into_view_if_needed()
            await page.wait_for_function("document.querySelector('.delivery-zone-preview')?.complete && document.querySelector('.delivery-preview-basemap')?.naturalWidth>0")
            after=await page.locator('.controller-float,.delivery-preview-map').evaluate_all('(els)=>els.map(e=>e.offsetHeight)')
            assert heights==after,('decoration changed size',width,heights,after)
            assert not errors,errors
            await page.screenshot(path=str(out/f'{width}-delivery.png'))
            result.append({'width':width,'initialDecorationRequests':0,'reservedHeights':heights,'loadedHeights':after,'loadedDecoration':distant(),'errors':errors})
            await context.close()
        await browser.close()
    (out/'result.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({'cases':len(result),'status':'PASS','evidence':str(out)}))

asyncio.run(main())
