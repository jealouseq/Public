"""Standalone FAQ pages resolve in both languages and the homepage stays focused."""
from playwright.sync_api import sync_playwright, expect

with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox'])
    page = b.new_page(viewport={'width':390,'height':844}, reduced_motion='reduce')
    for path, lang, question, button in [('/faq/','uk','Що потрібно для підключення?','Обрати дати'),('/faq-ru/','ru','Что нужно для подключения?','Выбрать даты')]:
        response = page.goto('http://localhost:8080'+path)
        assert response.status == 200, (path,response.status)
        expect(page.locator('html')).to_have_attribute('lang',lang)
        expect(page.locator('.faq-list details')).to_have_count(7)
        page.get_by_text(question,exact=True).click()
        assert page.locator('.faq-list details').first.evaluate('(e)=>e.open')
        page.get_by_role('link',name=button,exact=True).last.click()
        page.wait_for_selector('#booking')
        assert page.locator('#faq').count()==0
        expect(page.locator('html')).to_have_attribute('lang',lang)
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    b.close()
print('PASS: UA/RU FAQ routes, seven interactive answers, localized booking return, no homepage FAQ/overflow')
