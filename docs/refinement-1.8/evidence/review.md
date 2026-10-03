# JOYRENT 1.8 independent review

Scope: the working change against `18f0d36`, with the user's subsequent compact-process, mobile-connector and landscape-map requests. Current packaged build: `main-DymdWJeq.js` / `main-CBL0otFI.css`.

## Final verdict

**Unresolved P0: 0 · P1: 0 · P2: 0.** Spec verdict: pass. Code quality verdict: pass. The root's two visual P2 findings are resolved and independently checked below. This review is limited to the changed UI, geography/loading, public-data packaging and regression boundaries; unrelated backend features were not re-audited.

## Resolved findings

- **P2 — icon contour weight.** Constant stroke 2 merged details at the enlarged comparison size and departed from the selected thin outline. `components/ui/playstation-controller.tsx:6` now uses optical stroke 1.25 at sizes up to 32px and 0.8 above that; the standalone 64px SVG uses 0.8. Story and Booking use 28px controller glyphs. The corrected source/image comparison and six independent UA/RU 320/390/1440px icon cases passed, including contained Booking controls at 320px. Geometry remains transparent/currentColor, decorative and non-focusable.
- **P2 — attribution covering a zone.** In the initial landscape build at 320px, the absolute credit strip covered the southern red-zone tip. Before evidence remains in `work/refinement-1.8/landscape-review/attribution-before/`. `src/story-refinement.css:65` now puts attribution in a static footer below the complete map viewport at widths up to 900px. Eight fresh current-build UA/RU 320/390/768/980px cases passed: all six projected zone extents stay inside the map and are disjoint from the credit rectangle. RU320, UA768 and RU980 captures were visually inspected; the complete southern tip is visible. Desktop attribution remains unobtrusive and outside the zone extents.

## Current map and process

`src/sections/Story.tsx:57` layers the real local basemap and exact zone SVG in one 640:440 viewport. Both images are lazy, asynchronously decoded and render without independent cropping or stretching. The dark landscape map sits beside fees above 900px and stacks above them at smaller widths. The original fonts remain consistent. The warm calendar callout at `Story.tsx:63` keeps the setting-derived free threshold and green/yellow qualification, with both-direction and outside-zone conditions below it. Green/yellow prices still come from settings; red remains the taxi rate. Owner copy, Russian fallback identification, pickup and the desktop/mobile opener positions are retained.

Each process icon sits beside its heading, with their combined visible union centered as one group and its paragraph centered below. The independent compact check passed 12 UA/RU cases at 320/390/701/768/980/1440px: single-line titles, no overlapping boxes, consistent tablet row alignment and a shorter process grid. The final mobile rule at `src/story-refinement.css:72` restores a warm 1×18px line in each 26px gap, leaving 4px above and below. Eight scoped UA/RU 320/390/700/701px connector cases passed visible color, centering, text clearance, last-item suppression and no overflow. Actual RU320 and UA700 captures show the lines clearly. Short desktop segments and combined title centering remain intact.

The corrected Tariffs anchor continues to target `#how-it-works` with existing document scroll padding. The interactive map lifecycle implementation is unchanged; current preview navigation does not eagerly load its Leaflet or geometry chunks.

## Geography, provenance and privacy

- All six SVG paths and all 219 original GeoJSON vertices independently match the shared uniform Mercator projection; maximum coordinate error is 0.00000069 SVG pixels. The SVG is 8,678 bytes and contains no scripts or external resource hrefs. Its soft glow changes paint only, while original geographic path coordinates remain intact.
- The 26,934-byte basemap is a 1280×880 raster for the same logical 640×440 bounds, with zero crop offset. Its manifest projection equals the overlay projection. Raster and source-extract SHA256 values match the manifest.
- All 2,807 exported way coordinate sequences match the raw OpenStreetMap extract exactly. The public database contains 2,809 features including two original water relations. Coastline joins use shared endpoints; sea closure edges lie outside the visible bounds. The renderer uses those source coordinates and projection directly, without fabricated roads or visible coastline.
- The city marker is exactly 46.4825, 30.7233. The localized sea label projects the independently verified water point 46.44, 30.95. Localized labels remain within the viewport. Visible OpenStreetMap attribution links to its copyright page; the ODbL license, reusable public source database and manifest are included in both archives.
- Only whitelisted public cartographic tags are exported. There are no plugin/runtime settings, orders or private notification-email changes. No new eager dependency or remote font/map fetch was introduced for the static preview.

## Verification and package equality

The initial landscape build passed 18 scoped map/fee cases, including both sides of the 700/701 and 900/901 breakpoints. Those before-attribution measurements are preserved and are not presented as proof of the attribution fix. The final eight-case run separately verifies the corrected credit and full zone visibility on `main-DymdWJeq.js`, along with aligned image layers, contained labels/callout, local asset loading and no horizontal overflow, exceptions or external requests. Scripts, receipts and actual cropped captures are under `work/refinement-1.8/landscape-review/` and `connector-review/`.

Current theme and preview ZIP checks passed for nine relevant files each: final main JavaScript/CSS, entry/manifest metadata, new basemap and zone assets, source database, manifest and license. These files equal their reviewed source/build counterparts byte for byte. Preview README points to the bundled license. `scripts/package.mjs` copies only the public map-data directory into preview. Root separately reports final typecheck/build and 46 existing tests passing; independent diff-whitespace checks passed. No production code, build or package was edited during this review.

Reports for earlier `main-i_aDCQ0R.js`, `main-By-zN2f-.js` and compact/no-line `main-seI-bBsh.js` remain unchanged in the first-, second- and third-iteration review archives. All browser requests were guarded before transmission: only local GET/HEAD were allowed; non-GET and external requests were blocked. No contact data was entered and no order or email was sent.
