# JOYRENT1.4.1 — design QA

Source visual truth: user's latest1.4 screenshot and the approved change to increase PS5/DualSense prominence, shift the desktop scene toward copy and apply a smaller mobile increase. Matched source captures: `docs/refinement-1.4.1/before-hero-1440.png` / `before-hero-390.png`. Selected generated desktop target: `wordpress/joyrent/assets/images/ps5-commercial-desktop-close.png`, native1448×1086; mobile target remains the original native studio photograph. Actual implementation captured from anonymous local WordPress in Chromium, UA/RU, reduced motion and DPR2.

## Findings and comparison history

- P2, original: the product group had too little visual weight and too much empty space around it. Fix: one native ImageGen closer-frame edit for desktop plus110% picture geometry/left−10%; mobile uses the unchanged photograph at112%/left−6%. The new desktop console is approximately25–27% larger. Native framing has slight perspective/proportion differences; this is not claimed as an exact uniform transform of the original raster. Controller remains visually natural.
- P2, iteration1: a97% bottom fade left the illuminated floor ending too abruptly. Evidence: `iteration-1-floor-1440.png` / `iteration-1-floor-390.png`. Fix: desktop fade starts92%, mobile90%; the full console/stand silhouette stays before the fade. Post-fix full-view comparisons and `scene-detail.png` show a soft floor transition.
- No actionable P0/P1/P2 remains. Independent review confirms full products, clean blending, no overlap, unchanged content/hero geometry and sufficient native DPR2 density.

## Comparison evidence and normalization

- `comparison-hero-desktop.jpg`: source/final1440×1000 pixels and CSS viewport, DPR1, UA/top/reduced motion; both identically normalized to1100×764 and placed in one2200×764 input.
- `comparison-hero-mobile.jpg`: source/final390×844 pixels and CSS viewport, DPR1, same state; unscaled combined780×844 input.
- Both combined comparisons were opened and inspected. Source/current desktop native masters were also inspected. Focused `scene-detail.png`, an unscaled460×560 final crop, checks console top, base, controller anatomy, shadow and edge blending.
- Typography/control positions remain identical to the baseline and are legible in full comparisons; the user's change does not alter their font or copy. No additional typography-only crop was needed for this scoped size adjustment.
- `hero-1440-retina.png`2880×2000 and `hero-390-retina.png`780×1688 at DPR2 used for image quality checks, not treated as DPR1 baseline comparisons.
- Final captures cover320/360/375/390/414/430/768/900/901/1366/1440/1600/1920/3355, with UA/RU examples.41 initial geometry/loading scenarios plus18 independent final UA/RU DPR2 cases; final opacity-only fade does not change the measured geometry. Fresh final captures followed the fade fix.

## Required fidelity surfaces

- **Fonts/typography:** existing Unbounded550, tracking/line-height/wrapping unchanged. H1 remains accessible in UA/RU; decorative letter spans hidden from assistive technology. Content positions match1.4; reduced motion displays all letters immediately.
- **Spacing/layout:** fixed4:3 figure reserves original geometry while the picture becomes larger within it. Desktop hero stays680px at1440/1920; mobile390 stays~688px. Whole product bounds remain inside hero, with positive gap from heading or actions, no horizontal overflow. Text, CTA, price and section boundary match baseline.
- **Colors/tokens:** original near-black/off-white/amber tokens preserved; light remains part of the matte photograph. Slightly larger amber area follows the product; bottom fade and luminance blend remove the image rectangle.
- **Images:** complete PS5/stand/one DualSense, real generated product imagery without CSS art substitutes. New desktop master native1448×1086, full and768px WebP; mobile1120/560 retained. Maximum desktop canvas~701CSSpx and mobile~493CSSpx are covered at DPR2 without upscaling PNG. Independent minimum source densities2.065desktop/2.273mobile. Generated close framing changes the controller ratio slightly, but visual anatomy/proportions are natural, not an oversized device.
- **Copy/content:** original title, description, price, primary action, link and all other sections retained. No changes to rental logic/backend/settings or new marketing claims.

## Verification

`npm test`16/16; TypeScript/Vite build and changed-PHP lint pass.41 responsive/loading cases check full product bounds, no copy overlap/overflow, one applicable image from preload, correct sources and DPR2 coverage; maximum normal local CLS~0.00032. Independent final18 UA/RU cases at360–3355 verify unchanged hero/content rectangles, final masks, full products and zero page errors. CTA switches PS4→PS5 and navigates#rates; reduced motion works. Native image dimension/ZIP/dependency metadata checks pass. Plugin1.3 source and archived content unchanged.

Evidence: `docs/refinement-1.4.1/hero-viewport-results.json`, `independent-summary.json`, `independent-cta-results.json`. Production hosting/network metrics were not claimed; the live host was not modified.

## Implementation checklist

- Source/final imagery and paired full-view comparisons inspected.
- Focused product/floor detail checked after fade correction.
- Responsive, UA/RU, DPR2, preload, CTA and reduced motion verified.
- New theme/preview1.4.1 ZIP prepared; existing plugin1.3 retained.

Follow-up polish: none required for this scope. Native density beyond tested2× remains bounded by supplied assets.

final result: passed
