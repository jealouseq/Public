# JOYRENT 1.2 — design QA

Source truth: the user's new PS5, DualSense, rental-process and game-dialog screenshots; exact existing implementation captured before this iteration in `docs/refinement-1.2/before-*.png`. New product source: `wordpress/joyrent/assets/images/ps5-hero-cutout.png` (1024×1536, transparent, amber lighting). Intended changes explicitly requested: new hero composition and readable type, soft controller movement/light, simpler process, centered full-image dialogs and separate bilingual FAQ. This is a scoped redesign, not pixel-identical cloning.

Implementation: actual Chromium-rendered WordPress at localhost:8080, anonymous UA/RU. Source and final screenshots were opened and compared, including combined inputs; build success is not the visual evidence.

## Evidence and normalization

- Full-view comparisons: `docs/refinement-1.2/comparison-hero.jpg`, `comparison-rental.jpg`, `comparison-dialog.jpg`. Left is before, right final. Originals both 1440×1000 pixels/CSS viewport, deviceScaleFactor1; combined inputs use identical 1200×834 normalization for each side. Hero/dialog states match. Process captures include different neighboring content because the requested section simplification changes height; judge the process itself.
- Focused typography/framing: `hero-1440-uk.png`, `hero-390-uk.png`, `hero-390-ru.png`, `hero-320-uk.png`, `hero-3355-uk.png`. Portrait source asset composited on exact site background was also inspected in `work/refinement/hero-alpha-inspection.png` before use.
- Dialog detail: `dialog-1440.png`, `dialog-390.png`, `dialog-320.png`, plus 768/3355 and short landscape844. Source and opened artwork widths are measured, with <1.5px tolerance for rounding.
- Controller/process: `kit-1440-uk.png`, `how-it-works-1440-uk.png` and both language390px captures. Browser-rendered 12-second `hero-motion.webm` / `dualsense-motion.webm` show actual loops.
- Native FAQ/menu: `faq-1440-uk.png`, `faq-320-uk.png`, `faq-open-390-ru.png`, `menu-390-uk.png`. New FAQ is intentionally a separate route, not the previous landing-page section.

## Findings and comparison history

1. P1: hero photo felt bounded/cropped and dense display lettering merged. Replaced by padded transparent product photo in an independent column; Onest570, moderate tracking, two readable lines. Post-fix hero screenshots show complete products and no image rectangle; 390px product is visible without an overlaid rental bar.
2. P2: static controller loop in the first new runtime check. Explicit initial transform now starts the12-second loop; identity first/last frames eliminate jumps. Actual transforms change, image layout width stays fixed, and reduced motion stops both motion and light immediately.
3. P1: Tailwind preflight removed native dialog centering. Restored fixed inset0/marginauto, constrained to viewport; before x/y0, final centered at all six tested dimensions.
4. P2: square covers were forced into tall frames with empty black bands and enlarged on open. Natural image height, captured card width, separate natural artwork/text frames and no hover zoom preserve the complete image. Final focused desktop/mobile captures have no internal bars.
5. P2: live-resize700→701 initially produced a55px text column with the captured large art. Runtime viewport listener now uses a stacked composition whenever source width plus a readable text column cannot fit. The same open dialog remains readable through701/768/390/1440, with no overflow.
6. P2: rental process and delivery competed in one dense strip. Shortened three meaningful actions with one sentence each; delivery has one dedicated panel and free-delivery condition.
7. P2: FAQ mobile native header wrapped its long CTA and had double dividers. Short label and compact small-screen language header fix the wrapping; dividers use one line. UA/RU FAQ pages and matching-language booking return verified.

## Required fidelity surfaces

- Fonts/typography: Onest local Cyrillic/Latin for hero, process labels and modal/native FAQ headings; Unbounded brand/section hierarchy and Manrope body retained. No truncation, merged hero letters or hidden heading; UA/RU wrapping inspected from320 through3355.
- Spacing/layout: whole product remains within its allocated frame, generous content/image spacing, phone stacks, non-overlapping header controls, natural image dimensions and centered dialogs. Section changes are intentional, requested reductions.
- Colors/tokens: nearly black, off-white and amber preserved. Lighting uses the real image pixels with masks/opacity; no unrelated neon, drawn product replacements or artificial decorative counters. Focus contrast remains visible.
- Images: transparent1024×1536 product WebP≈83KB, full PS5/controller with clean edges; supplied successful DualSense cutout reused. All12 official game covers remain; no cropping/zoom on open. Icons use Phosphor; FAQ uses native disclosure markers.
- Copy/content: clear selection→confirmation/transfer→play/return sequence. Real city/deposit/short delivery remain owner-controlled and never invented. FAQ exists only on separate native UA/RU pages; price data and booking logic retained.

## Verification

16 unit tests pass. Full main browser suite passes11 widths320–3355 plus actual local request, dates/filter/carousel/dialog/consent/focus/mobile menu. Dialog regression suite passes centering, image ratio and source size, short-height action reachability and live resizing. Bilingual FAQ pages and static preview return to the correct-language booking page; existing owner pages are preserved by insert-only migration. Bilingual form and network errors, actual light opacity0.030→0.043→0.079, moving fixed-width controller and dynamic reduced motion pass. TypeScript/Vite, all PHP lint and three ZIP integrity checks pass. Independent read-only review approved final resize correction; no blocking findings.

No actionable P0/P1/P2 findings remain. Browser QA is local; this iteration does not claim the external WordPress host has been updated. Existing owner-customized FAQ text may need editing by the owner. Follow-up polish is subjective preference after viewing the new composition.

final result: passed
