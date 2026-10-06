# JOYRENT 1.8 — independent packaged browser acceptance

**12 current cases PASS / 0 FAIL.** UA/RU at 320,390,768,980,1440,1920px on actual packaged WordPress `http://localhost:8080`. Final entry `main-i_aDCQ0R.js`, CSS `main-Dr9ihFq-.css`; freshly served bytes match the source package. Theme1.8.0; rentals plugin remains1.7.0.

The twelve cases verify actual tariff-link click to `#how-it-works` with the language query preserved and heading visible; node/body/title and each glyph-line centers within2.5px; desktop symmetry and mobile grid alignment; open connected process steps; portrait preview without distortion; desktop opener above the map layout at the header right; the existing centered mobile pill after fee conditions (196×52px,999px radius); visible keyboard focus and44px targets; no horizontal overflow, page exceptions or unexpected first-party request failures.

Map checks verify no Leaflet or GeoJSON requests before opening, one query-free main entry, six exact zone paths, green-zone price selection/reset, native dialog Escape/button close, reopen and focus return, and the original main DOM node remaining stable. All external OSM raster GET requests were deliberately blocked; dialog screenshots show the local vector scheme. The static preview is a locally loaded SVG with six identified zone paths; its served bytes match the package.

The final attached detailed controller is shared by the final process step and both Booking options: viewBox64, stroke2/currentColor, transparent fill, no rotation, no clipped stroke. Both one/two-controller choices change `aria-pressed` and visible border/text styling with≥44px targets. Separate browser-only catalog GET fixtures verify the same original vector at42px in the missing-cover UI; server catalog bytes remained unchanged. These supplemental observations do not add release case totals.

| Case | Current result | Immutable run |
|---|---|---|
| viewport-uk-320 | PASS | 20261003T114503Z |
| viewport-uk-390 | PASS | 20261003T114503Z |
| viewport-uk-768 | PASS | 20261003T114503Z |
| viewport-uk-980 | PASS | 20261003T114503Z |
| viewport-uk-1440 | PASS | 20261003T114503Z |
| viewport-uk-1920 | PASS | 20261003T114503Z |
| viewport-ru-320 | PASS | 20261003T114503Z |
| viewport-ru-390 | PASS | 20261003T114757Z |
| viewport-ru-768 | PASS | 20261003T114503Z |
| viewport-ru-980 | PASS | 20261003T114503Z |
| viewport-ru-1440 | PASS | 20261003T114503Z |
| viewport-ru-1920 | PASS | 20261003T114757Z |

**History retained:** initial run114503Z contained10 PASS and2 FAIL (RU390/RU1920). Both failures were harness rendering-frame timing: separate computed-style evaluations crossed a reduced-motion CSS transition frame. Timed diagnostic samples show previous styles at frame0 and correct selected/unselected styles after32ms. The harness now waits two animation frames and samples both options atomically. Only those two contexts were retested (114757Z), both PASS; production code did not change. Original failures/screens and diagnostic remain available, with zero product defects found by this focused acceptance.

**Captures:** twelve selected actual viewport PNGs in `docs/refinement-1.8/screens`, UA/RU390/1440 process, delivery and map. Four Booking controller screenshots are included as additional visual evidence. The final mobile delivery captures focus on the whole map/fee/pill composition; their original heading/intro captures remain in the first timestamped run. All images use DPR1/reduced motion, fonts and visible images loaded/decoded, incidental focus blurred without hiding accessibility CSS. Screenshot SHA256 and original paths are in `browser-selected-screens.json`. Principal screenshots and contact sheets were visually inspected.

Fresh live/local pre-change evidence is kept separately in `baseline-report.md` and the two `*-baseline` directories. Both origins served exact1.7 frontend bytes at11:32UTC. Live saved delivery intro is concise; local saved intro repeats the fee table. QA changed neither setting and does not claim this local release is deployed live.

All POST/non-GET/HEAD requests were guarded before allowlist/continue. No contact data entered, no order/email sent and no production/server mutations performed. Only audited local GET/HEAD were transmitted during final QA. Native PHP/backend scenarios and archive validation are outside this focused browser acceptance.
