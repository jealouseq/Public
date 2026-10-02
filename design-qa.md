# JOYRENT 1.1 — design QA

Date: 2026-10-03. Source truth: the user's three annotated screenshots and fresh browser captures of https://flowers-luxury.shop/ in `docs/audit-2026-10-03/live-*.png`. Implementation: browser-rendered local WordPress at http://localhost:8080, `new-*.png` in the same directory. This is an authorized refinement of the existing design, with deliberate changes to crop, structure, artwork and bilingual content.

## Evidence

- Desktop source and result: 3355×1274 pixels, CSS viewport 3355×1274, deviceScaleFactor 1, anonymous Ukrainian homepage, reduced motion. [Combined full view](docs/audit-2026-10-03/comparison-hero-wide.jpg): left source, right updated. Each original was downsampled identically to 1500×570 for comparison.
- Mobile source and result: 390×844 pixels/CSS viewport, density 1, same UA homepage/reduced-motion state. [Combined full view](docs/audit-2026-10-03/comparison-hero-mobile.jpg), original density preserved.
- [Controller comparison](docs/audit-2026-10-03/comparison-controller.jpg) and [FAQ comparison](docs/audit-2026-10-03/comparison-faq.jpg): both source/result 3355×1274, same section scroll-into-view operation. Adjacent sections intentionally differ because the controller/kit merge and shorter final CTA alter heights and reading order.
- Focused readable evidence: `new-hero-1440-uk.png`, `new-kit-1440-uk.png`, `new-faq-1440-uk.png`; both UA/RU mobile captures for all sections. Exact-game MK11 and Tekken 8 artwork was opened and visually checked against named official source pages.

## Findings and comparison history

- P1: ultrawide and mobile hero cropped the console/controller. Fixed with a bounded image scene and contained artwork; post-fix matched hero captures show whole objects.
- P1: MK11, Tekken 8, UFC 5 and MK1 shared invented artwork. Fixed with twelve distinct official exact-game covers. Original embedded logos remain in the images; redundant UI poster titles removed.
- P2: controller width/zoom scroll motion and huge close-up. Replaced by full transparent cutout with a fixed layout and restrained eight-second transform loop; contents merged into this section.
- P2: repeated kit/process/delivery copy and field counters. Reduced to one kit section, three process phases and concise delivery conditions before the form. Numbered form labels and repeated rental-price line removed.
- P2: FAQ and huge final CTA merged visually. Concrete FAQ title and seven useful questions; separate compact amber panel.
- P2: bilingual error and native legal navigation gaps found in review. Corrected transport/delayed errors, cleared stale-language errors on switching, translated native templates and preserved RU in return links.
- P2: first runtime motion test found an idle loop caused by blocked initial motion. Explicit starting states now run both loops. Final browser samples show hero opacity changing 0.040→0.088→0.220, controller transforms changing while layout width stays fixed. Reduced motion now responds immediately to a preference change and stops both loops.
- A first 320-pixel resize test reported overflow; repeated isolated/batch checks and the final full viewport suite no longer reproduce it. No content/control is outside the document width in final checks, including 320 pixels.

## Required surfaces

- Typography: self-hosted Unbounded/Manrope preserved; Cyrillic works in both languages. Hero lines, prices, mobile wrapping and native legal headings reviewed. No basic-font substitution.
- Spacing/layout: bounded desktop composition, full mobile product, merged controller/kit, three process columns and stacked mobile layout, readable FAQ and visibly separate CTA. Changes from source are intentional.
- Colors: existing nearly black/white/amber tokens retained. Warm lighting is an animated pass over the actual photograph. Controls retain visible focus indicators and muted text remains readable.
- Images: whole products, clean alpha composited on site background, twelve actual named game covers contained without logo cropping. Game brands/images are raster artwork, icons use Phosphor. Owner thumbnails have priority.
- Copy: generic repetitive slogans/counters removed; concrete rental journey, no invented deposit/city/availability. UA/RU menus, form, dialogs, game descriptions, errors, receipts, legal pages and footer reviewed. Untranslated custom owner delivery text remains visible with an explicit language label; owner can supply RU in settings.

## Interaction verification

Chromium tested 320/360/390/430/768/1003/1280/1440/1920/2560/3355 widths. Console/date changes, game filters/carousel/dialog/focus, legal dialog, consent, actual local WooCommerce request, immutable receipt, mobile navigation, hidden sticky CTA around booking, language/form persistence, network-error retry key, normal motion and dynamic reduced motion pass. Browser error checks pass; all official covers decode. Live hosting was inspected read-only; no live order or payment was sent.

No actionable P0/P1/P2 visual findings remain. The revised frontend is verified on the local WordPress fixture; the live host still needs the 1.1 theme/plugin update.

final result: passed
