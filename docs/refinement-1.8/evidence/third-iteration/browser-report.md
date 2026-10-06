# JOYRENT 1.8 — independent packaged browser acceptance

**12 current cases PASS / 0 FAIL.** Every case freshly rerun on the final compact process build, 20261003T121640Z: UA/RU at320,390,768,980,1440,1920px on actual packaged WordPress `http://localhost:8080`. Final entry `main-seI-bBsh.js`, CSS `main-CyZhN2wL.css`; freshly served bytes match the source package. Theme1.8.0; rentals plugin remains1.7.0.

The twelve cases verify actual tariff-link click to `#how-it-works` with the language query preserved and heading visible; actual visible icon/title cluster-edge midpoint within2.5px of each item center, single horizontal row with12px gap and no wrapped titles; description10px below with each paragraph line centered; desktop symmetry/short gap connectors and mobile grid alignment/26px step spacing without obsolete connectors; portrait preview without distortion; desktop opener above the map layout at the header right; the existing centered mobile pill after fee conditions (196×52px,999px radius); visible keyboard focus and44px targets; no horizontal overflow, page exceptions or unexpected first-party request failures.

Map checks verify no Leaflet or GeoJSON requests before opening, one query-free main entry, six exact zone paths, green-zone price selection/reset, native dialog Escape/button close, reopen and focus return, and the original main DOM node remaining stable. All external OSM raster GET requests were deliberately blocked; dialog screenshots show the local vector scheme. The static preview is a locally loaded SVG with six identified zone paths; its served bytes match the package.

The attached detailed contour is shared by the final process step and both Booking options: viewBox64, size28px with optical stroke1.25/currentColor, transparent fill, no rotation, no clipped stroke. Both one/two-controller choices change `aria-pressed` and visible border/text styling with≥44px targets. Separate browser-only catalog GET fixtures verify the same vector at42px with stroke0.8 in the missing-cover UI; server catalog bytes remained unchanged. These supplemental observations do not add release case totals.

| Case | Current result | Immutable run |
|---|---|---|
| viewport-uk-320 | PASS | 20261003T121640Z |
| viewport-uk-390 | PASS | 20261003T121640Z |
| viewport-uk-768 | PASS | 20261003T121640Z |
| viewport-uk-980 | PASS | 20261003T121640Z |
| viewport-uk-1440 | PASS | 20261003T121640Z |
| viewport-uk-1920 | PASS | 20261003T121640Z |
| viewport-ru-320 | PASS | 20261003T121640Z |
| viewport-ru-390 | PASS | 20261003T121640Z |
| viewport-ru-768 | PASS | 20261003T121640Z |
| viewport-ru-980 | PASS | 20261003T121640Z |
| viewport-ru-1440 | PASS | 20261003T121640Z |
| viewport-ru-1920 | PASS | 20261003T121640Z |

**History retained:** initial run114503Z contained10 PASS and2 FAIL (RU390/RU1920). Those two failures were harness rendering-frame timing: separate computed-style evaluations crossed a reduced-motion CSS transition frame. Timed diagnostic samples show previous styles at frame0 and correct selected/unselected styles after32ms. The harness now waits two animation frames and samples both options atomically; scoped retests114757Z passed before any product change. Original failures/screens and diagnostic remain unchanged.

**Real product finding:** root's512px reference/vector comparison then identified P2 icon fidelity: stroke2 was roughly3× heavier than the selected generated image and dense controls looked solid. This was an external visual review finding, not a browser assertion failure. The final correction preserves contour geometry, uses stroke0.8 on standalone/42px art and stroke1.25 at the larger28px Story/Booking size. The first-iteration report, receipt and16 screenshots are archived under `evidence/first-iteration` and `work/refinement-1.8/first-iteration`. The corrected build received all12 fresh cases plus updated visual inspection; no unresolved product issue remains in this scope.

**Latest requested composition:** after the corrected-icon second iteration passed all12 cases, the user explicitly requested the icon beside its title with tighter spacing. That second iteration is archived under `evidence/second-iteration` and `work/refinement-1.8/second-iteration`. The current pair-midpoint assertions reflect this new visual specification; separate icon/title-center assertions from the former stacked design are intentionally replaced. The description remains centered below the pair.

**Captures:** twelve selected actual viewport PNGs in `docs/refinement-1.8/screens`, UA/RU390/1440 process, delivery and map. Four Booking controller screenshots are included as additional visual evidence. The final mobile delivery captures focus on the whole map/fee/pill composition; their original heading/intro captures remain in the first timestamped run. All images use DPR1/reduced motion, fonts and visible images loaded/decoded, incidental focus blurred without hiding accessibility CSS. Screenshot SHA256 and original paths are in `browser-selected-screens.json`. Principal screenshots and contact sheets were visually inspected.

Fresh live/local pre-change evidence is kept separately in `baseline-report.md` and the two `*-baseline` directories. Both origins served exact1.7 frontend bytes at11:32UTC. Live saved delivery intro is concise; local saved intro repeats the fee table. QA changed neither setting and does not claim this local release is deployed live.

All POST/non-GET/HEAD requests were guarded before allowlist/continue. No contact data entered, no order/email sent and no production/server mutations performed. Only audited local GET/HEAD were transmitted during final QA. Native PHP/backend scenarios and archive validation are outside this focused browser acceptance.
