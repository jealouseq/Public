# JOYRENT — Product Design QA

**Source visual truth:** `assets/design/homepage-01.png` (Studio Editorial), selected by the implementer under the user's explicit instruction to choose the stronger direction and build. Typography was refined under the later request for distinctive fonts.

**Implementation:** actual installed WordPress theme at `http://localhost:8080`; `docs/images/desktop-comparison.png`.

**Viewport and normalization:** source 1003 × 1568 pixels; implementation 1003 × 1568 CSS/pixel viewport, deviceScaleFactor 1. No browser frame, resizing or density mismatch. State: Ukrainian, PS5, initial 3-day form, full catalog, near-black theme, reduced motion for stable comparison.

**Full-view comparison:** `docs/images/design-comparison.png` contains both images at the same scale: source left, implementation right. **Focused comparison:** `docs/images/hero-comparison.png` compares the first 720px at 1:1 scale. Font, image crop and CTA hierarchy are readable there. Additional browser evidence: `docs/images/desktop.png`, `desktop-full.png`, `mobile-hero.png`, `mobile-games.png`, `mobile-booking.png`.

## Findings and iteration history

1. **[P2, fixed] Display text and prices too small at desktop 1003px.** First comparison `docs/images/design-comparison-before.png` showed the heading losing the source's dominant hierarchy. Increased desktop hero scale from 5.65vw to 7.2vw (cap 92px), section heading scale to 4.4vw and tariff prices to 4.4vw. Reduced excess tariff spacing. The revised full/focused comparison restores large type beside the console while retaining readable body copy.
2. **[P2, fixed] Horizontal overflow from off-screen carousel descriptions.** Initial rendered HTML measured 3395px on a 1440px viewport. Positioned `.game-card` as the containing block for its screen-reader text. Final browser measurements match viewport width at 360/390/430/768/1003/1280/1440px.
3. **[P1, fixed] WordPress assets/boot configuration missing after the first production build.** Preserved the inline WordPress configuration when marking the entry script as a module, and normalized package file permissions. Revised WordPress evidence has the hero, posters and fonts loaded; no failed HTTP assets or JS exceptions.
4. **[P2, fixed] Mobile bottom CTA overlapped the form in the section capture.** Hide the persistent CTA while the booking section intersects the viewport. Form controls and summary use the same clear column; the primary form button remains available.

## Required fidelity surfaces

- **Fonts/typography:** self-hosted Unbounded Variable for display/price and Manrope Variable for text; Ukrainian Cyrillic font loaded and checked in Chromium. The selected image's conventional grotesk has intentionally been replaced by the more distinctive type requested by the user. Three-line hero hierarchy, large prices and compact labels remain. No headline truncation at tested widths.
- **Spacing/layout rhythm:** near-black full-width hero, left type/right product, open four-column tariffs and horizontal poster rail retained. Tariffs become two columns on phones; booking becomes one column. The implemented rail begins lower than the concept because real delivery conditions, descriptions and category controls are now present. This is an intentional information addition, not an attempt at identical screenshot cloning. Generous section spacing remains; no overlap or page overflow.
- **Colors/tokens:** #08090b background, #f4f4f1 foreground, restrained #eab56e accent, muted greys and subtle dividers. No neon gradients or decorative blobs. Active controls and focus rings remain distinct.
- **Image quality/fidelity:** real generated PS5/DualSense studio photo follows the source's black/amber direction. Product and game images are sharp optimized WebP. Original generated raster artwork is preserved; no CSS/SVG substitutes for imagery. Game posters are original themed illustrations with honest UI titles, rather than invented official packaging. Owners can replace them with verified edition assets.
- **Copy/content:** Ukrainian navigation, source hero copy, exact seven supplied prices and clear rental terms. No invented same-day delivery, city, deposit, stock or payment claims. Request and confirmation wording agree with the actual WooCommerce pending-request state. Developer instructions do not leak into product copy.

## Interaction/accessibility evidence

Automated Chromium checks exercised tariff selection, PS5→PS4 one-day fallback, calendar return date, game filter, horizontal arrow scrolling, game dialog/selection, Escape/focus restoration, legal dialog, required consent, real request creation and immutable success details. Mobile navigation closes after selection. Page language is `uk`; named native dialogs provide focus trapping. All seven viewport widths pass without document overflow. Reduced-motion photo is static; normal scroll expands the reveal image from 1094px to 1418px at a 1440px viewport. Final run reports zero JS errors, failed image requests or missing loaded images.

## Accepted differences and remaining context

The concept is an art-direction source. Additional functional information and original game illustrations are expected differences. No mobile reference was supplied; mobile is reviewed for layout and task completion against the same brand direction. Real service conditions, inventory and contact details still need the owner's configuration before public launch. Automatic inventory reservation and payment are outside this version's request workflow.

## Implementation checklist

- [x] Large expressive type, local Cyrillic fonts and correct wrapping.
- [x] Original studio assets, optimized formats and loaded imagery.
- [x] Repaired overflow, production boot and mobile CTA overlap.
- [x] Checked desktop/mobile interactions, keyboard dialogs and reduced motion.
- [x] Compared revised full-view and focused visual evidence.

**final result: passed**
