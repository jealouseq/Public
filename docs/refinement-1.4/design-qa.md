# JOYRENT 1.4 — design QA

Source visual truth: user's latest live-site screenshot, matched anonymous 1.3 captures `docs/refinement-1.4/before-hero-1440.png` and `before-hero-390.png`, and approved black/white/amber studio direction. Scope intentionally changes the dominant full-hero photograph into a separate right column. Final generated targets: `wordpress/joyrent/assets/images/ps5-commercial-desktop.png` and `ps5-commercial-mobile.png`, both native1448×1086. Their full products, matte floor and warm light are the asset fidelity targets.

Implementation: actual local WordPress at localhost:8080 in Chromium, UA/RU, anonymous. Source assets and rendered pages were opened; combined comparisons and focused text/scene views were inspected before this report. This is a scoped refinement, not a pixel clone of the superseded layout.

## Findings and comparison history

1. P1, original: `.hero-scene` stretched across the useful hero width, allowing the console to dominate and approach the headline. Evidence: matched before1440 and new desktop full-view comparison. Fix: figure inside the grid, roughly52/48 columns, explicit gap, complete4:3 composition in the right column. The post-fix1440/1366/1920 renders show visible separation, a grounded full PS5 and proportional single controller.
2. P2, asset iteration: first desktop generation made the controller too large and placed the group too far right. It was regenerated with physical proportions, retained as a native master, then compared with the final scene detail. The old wet-looking floor is now matte, with short subtle reflection and contact shadows.
3. P2, integration iteration1: near-black source against page `#08090b` exposed a rectangular panel. Evidence: `iteration-1-hero-1440-uk.png` and `iteration-1-hero-390-uk.png`. Fix: luminance blending plus edge-only masks. Post-fix combined comparisons and `scene-detail.png` show no hard rectangular edge; top, base and controller remain complete.
4. P2, cold-font test: at320UA and360RU, body-font fallback changed description from three lines to two and moved actions24px. Diagnostic before/after font measurements established the cause. Fix: reserve three description lines only at≤375px; existing stable headline reservation retained. Independent delayed-Manrope checks show0px action and figure movement at320UA/360RU, with390RU control. Final phone captures confirm the extra space remains compact.
5. P2, independent review: `(max-width:900px)` / `(min-width:901px)` left a fractional gap in preload and final tablet styling. Fix: complementary mobile≤900/desktop>900 conditions and `(900px < width <=1200px)` override. Canonical PHP/React media selection stays aligned; normal900/901 cases and browser/source review pass.

No actionable P0/P1/P2 remains.

## Normalization and visual evidence

- Desktop full-view: `comparison-hero-desktop.jpg`. Both source/implementation1440×1000 image pixels, CSS viewport1440×1000, DPR1, same top/UA/reduced-motion state. Each identically downsampled to1100×764, placed adjacent in one2200×764 comparison input.
- Mobile full-view: `comparison-hero-mobile.jpg`. Both390×844 image pixels/CSS size, DPR1, same state, no resizing; combined780×844. Final scene fits inside the first section, rather than extending beyond the viewport. Current390 hero height~688px vs~804px before, excluding unchanged header.
- Asset direction: `comparison-studio-assets.jpg`. Old1672×941 and new1448×1086 source images proportionally contained in equal900×675 panels. Aspect ratio change is intentional; neither source is stretched. Matte floor, contact shadows, physical scale and warm light inspected together. Separate mobile master inspected against its rendered scene.
- Focused typography: `headline-detail.png`, native690×190 crop from1440 final. Family, accents, punctuation, spacing and whole-word boundaries remain legible. No merged letters or clipping.
- Focused image/blending: `scene-detail.png`, native610×490 crop from1440 final. Console, stand and controller complete; edge blending, rim light and restrained reflection checked at larger scale.
- Retina: `hero-1440-retina.png`2880×2000 pixels and `hero-390-retina.png`780×1688 at DPR2, CSS sizes unchanged. Used for sharpness inspection; not treated as density-matched baseline comparisons.
- Other final renders:320/360/375/414/430/768/900/901/1366/1600/1920/3355, plus1440RU/390RU.46 measured scenarios are recorded in `hero-viewport-results.json`.

## Required fidelity surfaces

- **Fonts/typography:** existing self-hosted Unbounded550, display tracking−.035em/1.19line-height on desktop and−.03em/1.2 on mobile. Two desktop lines, three on small phones. One semantic H1 contains the complete accessible UA/RU phrase; decorative animated letters are hidden from assistive technology. Fonts preloaded without adding a font dependency. The native CSS entrance completes to opacity1/zero blur; reduced motion disables it.
- **Spacing/layout rhythm:** figure is a grid sibling, not section background. Desktop useful width bounded1536px; content and image have visible gap. Mobile puts actions/price before a centered visual bounded440px. Explicit intrinsic4:3 size and text reservation limit reflow. No overflow across320–3355px, breakpoint edges or short landscape. CTA/link/menu preserved.
- **Colors/tokens:** original near-black, off-white and JOYRENT amber. Warm light is photographic, quietly concentrated behind PS5 without neon/ring. Masks only fade edges; they do not draw product art. Existing button hover/focus feedback preserved.
- **Image quality:** two native opaque ImageGen masters, four high-quality WebP sizes, complete products and physical shadows. No placeholder shapes or synthetic SVG/div product. Native1448px desktop covers maximum~638px at DPR2; larger density remains limited by generator resolution. No upscaling misrepresented as native2K. Mobile art is independently composed, not a desktop crop.
- **Copy/content:** original UA/RU headline, rental description, links, price and primary action retained. No new counters, claims or store terms. Non-hero content and all backend source files unchanged.

## Interaction and technical verification

Fresh TypeScript/Vite build and16/16 unit tests pass. `browser.py` verifies actual local WooCommerce request, console/date safety, game filter/carousel/dialog/focus, consent, immutable confirmation and mobile menu; no JS or failed HTTP assets. `polish-browser.py` verifies bilingual form/selection/consent, localized failure/retry with stable requestId, all12 artworks, final native text state, static product and unchanged smooth controller/reduced motion.

Responsive matrix:38 UA/RU cases, three DPR2 and five artificially delayed-font cases. Every normal case loads one applicable hero image, initiated by the matching head preload. FAQ has no hero preload. Normal local CLS≤0.00032; representative390/1440 LCP~0.33–0.39s. Delayed-all-font global CLS~0.005–0.011 includes unchanged logo/tariff elements, while hero actions remain fixed. These are local unthrottled measures; production Core Web Vitals were not claimed.

All16 PHP files lint. Theme/preview1.4 ZIP integrity and asset contents pass; native PNG masters excluded. Plugin1.3 source and archive contents remain unchanged. Existing dependency metadata unchanged. Independent read-only review rechecked fixes and primary CTA semantics; no P0/P1/P2 remaining.

## Implementation checklist

- Source screenshot and new desktop/mobile generated targets inspected.
- Final full-view comparisons and focused typography/scene regions opened.
- Earlier P1/P2 findings, fixes and post-fix evidence recorded.
- Responsive, UA/RU, DPR2, reduced motion, delayed fonts and primary interactions verified in actual WordPress.
- Releases prepared for theme-only1.3→1.4 update; current local WordPress remains running.

Follow-up polish: no blocking polish item. Actual hosting/network CWV and density beyond supported native assets are the remaining environment limits. Host was not modified.

final result: passed
