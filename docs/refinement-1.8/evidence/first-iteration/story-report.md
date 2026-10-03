# Story composition refinement 1.8

Implemented the approved composition in `src/sections/Story.tsx`, `src/story-refinement.css`, and the new static asset `wordpress/joyrent/assets/images/delivery-zone-map-preview.svg`.

The delivery surface now has a clear heading and saved owner introduction above a dedicated map/fees row. A portrait map stage carries all six delivery polygons, a restrained geographic coordinate grid, and a localized Odesa label. The compact fees and existing free-delivery condition sit beside the preview at desktop widths; both stack at 700px and below. The desktop map pill sits at the heading's right. The mobile pill follows the fees with its existing centered 196×52px minimum size and 999px radius.

All process icons, titles, and paragraphs are centered. The desktop rail connects icon centers. Mobile uses a centered sequence with short connectors in the gaps between steps. The `how-it-works` anchor and process/map QA hooks are retained.

Saved owner text and all delivery terms are preserved. The existing lazy Leaflet dialog, complete GeoJSON, point classification, native focus lifecycle, and map controls were not changed. The static image introduces no eager Leaflet or geometry-chunk request. Original DualSense media and its existing paused/reduced-motion animation were not changed.

## Verification

- `npx tsc --noEmit`: passed.
- `npm test`: 46 tests passed in four files, including 17 map geometry/business-rule tests.
- `python3 work/refinement-1.8/story-qa/geometry-check.py`: passed. Six complete polygons retain all 219 approved source vertices; projected coordinates agree within one millionth of a SVG pixel. One conformal scale preserves aspect ratio and all pieces. Odesa dot and HTML label are aligned to longitude 30.7233, latitude 46.4825.
- `python3 work/refinement-1.8/story-qa/layout-check.py`: passed 11 cases: Ukrainian/Russian at 320, 390, 701, 980, 1440px and Ukrainian at 1920px. Measured icon/title/body glyph centers agree with step centers within 2.5px; mobile steps align to the grid center. Verified desktop/mobile action position, preview legibility/height, no horizontal overflow, lazy full map, six overlay polygons, blocked-tile fallback notice, Escape closing, focus return, and zero page errors.
- Before the centering change, the measured 980px regression check failed with a step center of 189.3px and icon center of 72.5px. It passes after the change.

The browser suite allowed local GET requests only and blocked all external requests and POST requests. It verifies the local map fallback and interaction, without claiming external basemap availability. Root owns final build/package and independent actual-WordPress browser checks.

Screenshots and measured results are in `work/refinement-1.8/story-qa/`. Useful review captures: `delivery-uk-1440.png`, `delivery-uk-320.png`, `delivery-ru-701.png`, `process-ru-980.png`, and `process-ru-320.png`. Sources are frozen for root build.
