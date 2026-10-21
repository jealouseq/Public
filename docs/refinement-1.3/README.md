# JOYRENT 1.3 — студийный главный экран

Возвращён фотографический фон с чёрной студией, янтарным светом и фактурным полом. Два новых кадра ImageGen: `ps5-hero-desktop`1672×941 и `ps5-hero-mobile`1254×1254. Desktop WebP107086bytes, mobile158920bytes; непрозрачный RGB. Исходный стиль — сохранённый `ps5-studio.webp`. PNG-мастера сохранены в репозитории, в архивы входят WebP.

Заголовок: локальный Unbounded550, умеренный tracking; две строки на ПК, перенос целых слов на телефоне. В `/components/ui/blurred-stagger-text.tsx` адаптирован предоставленный промпт [Blurred Stagger Text — Preet Suthar](https://21st.dev/@preetsuthar17/components/blurred-stagger-text). Буквы мягко проявляются один раз; reduced motion показывает их сразу. Используется установленный Framer Motion, дополнительный пакет `motion` не нужен.

## Просмотр

- [ПК1440](hero-1440-uk.png) · [ультраширокий3355](hero-3355-uk.png) · [планшет768](hero-768-uk.png).
- [ТелефонUA](hero-full-390-uk.png) · [телефонRU](hero-full-390-ru.png) · [320px](hero-full-320-uk.png).
- [До/послеПК](comparison-hero-desktop.jpg) · [до/послетелефон](comparison-hero-mobile.jpg) · [исходный/новый студийный кадр](comparison-studio-assets.jpg) · [типографика](headline-detail.png).
- [АнимацияПК](hero-motion-desktop.webm) · [анимациятелефон](hero-motion-mobile.webm). Это реальные12-секундные записи браузера.
- [28 замеровUA/RU](hero-viewport-results.json) · [DesignQA](../../design-qa.md).

Все финальные снимки сделаны в реальном локальном WordPress, без админ-панели. Предыдущие1.2 изменения — DualSense, окна игр, FAQ, UA/RU и оформление заявки — сохранены. Стенд на опубликованном домене этим архивом ещё не обновлялся.
