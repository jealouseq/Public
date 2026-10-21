# Final Story refinement 1.8

The final delivery composition follows the supplied landscape reference: heading and saved owner introduction above a wide geographic map and compact fees, desktop map action at the heading's right, and the existing centered mobile pill after the terms. The map takes roughly 55–57% of the desktop row; fees take the remaining right column. At 900px and below the map stacks above the fees to avoid narrow tablet pricing. The ≥7-day condition and existing delivery/return note share one quiet amber calendar callout.

The map combines real OpenStreetMap roads, coastline and water with all six approved delivery polygons. Odesa and the Black Sea labels use verified geographic coordinates and both site languages. The transparent zone overlay preserves the original geometry and uses .27 fill, a 1.3px non-scaling outline, and a restrained static 3px glow. A visible ©OpenStreetMap contributors link is included. No geography or road names were invented.

The compact process remains centered: each 40px icon and heading form one centered pair with a 12px gap; the paragraph sits 10px below. The selected detailed controller remains 28px and other icons 21px. Desktop has short horizontal connectors in column gaps. Mobile has two warm 1×18px vertical connectors confined to the existing 26px gaps. At 320/390px the grid stays ~342px high, preserving the 162–170px reduction from the earlier icon-above-title composition.

Saved owner text, fee settings, both-direction pricing, green/yellow-only free delivery, red taxi pricing, and outside-zone confirmation are preserved. The native full-map dialog, lazy Leaflet/GeoJSON loading, exact point classification, keyboard/touch controls, Escape closing, and focus return are unchanged. Original controller media and its motion safeguards are unchanged.

## Production files

- `src/sections/Story.tsx`
- `src/story-refinement.css`
- `wordpress/joyrent/assets/images/delivery-zone-map-landscape.svg` — 8.7KB exact vector overlay.
- `wordpress/joyrent/assets/images/delivery-basemap-odessa.webp` — 26.9KB actual OSM basemap; rendered by the reference agent.
- `wordpress/joyrent/assets/data/delivery-basemap-manifest.json`, `delivery-basemap-osm.geojson.gz`, and `delivery-basemap-LICENSE.md` — published ODbL attribution/source/projection receipt; supplied by the reference agent. Supporting source data is not requested by the frontend.

The two unused portrait preview assets were removed as instructed and preserved in `work/refinement-1.8/landscape-map/superseded-assets/`. All active preview references use the landscape SVG and WebP. Published files are 0644 and the data directory is 0755.

## Exact geographic receipt

Logical viewport: 640×440. Basemap raster: 1280×880, device scale 2, no crop or offset. All six polygons retain 219 source vertices. Uniform Web Mercator equations:

```
x = (longitude*pi/180 - center_x)*scale + 320
y = (-log(tan(pi/4 + latitude*pi/360)) - center_y)*scale + 220
scale = 55563.326671730516
center_x = 0.5362297870994994
center_y = -0.9178932585594465
```

WGS84 bounds: west 30.39372611729199, east 31.053681182707997, south 46.303969452017704, north 46.61651612659042. Zone extent inside the logical frame: x215.34177–424.65823, y22–418. City pin: longitude30.7233, latitude46.4825. Verified water label: longitude30.95, latitude46.44. The source extract contains 2,809 actual OSM features; no raster tile archive was used.

The generator, full coefficients, source SHA-256, and overlay manifest are in `work/refinement-1.8/landscape-map/`. The SVG embeds its projection/source receipt as metadata. The public basemap manifest records the matching projection, OSM timestamps, source/image hashes, and verified water point.

## Verification

- `npx tsc --noEmit`: passed after final Story changes.
- Earlier functional suite: all 46 tests passed in four files, including 17 map geometry/business-rule tests. Those helpers and rules were not changed by the landscape revision.
- `geometry-check.py`: passed after the final outline/glow adjustment. Six complete polygons/219 vertices agree with the uniform projection within one millionth of a SVG pixel; no stretch, crop, or omitted pieces.
- Basemap/source hash check: passed. Exact shared projection, no crop/offset, 2,809 source features, verified water point, and published ODbL source receipt.
- `layout-check.py`: passed 13 UA/RU composition/modal cases at 320,390,700,701,980,1440px plus Ukrainian1920px. Verified local basemap and overlay decode/alignment, true aspect, stage/fee proportions, responsive stack, labels, attribution, calendar condition, mobile pill placement, lazy full map, six vectors, fallback notice, Escape/focus, no overflow or errors.
- After the final legibility adjustment, `layout-check.py --legibility` passed the requested four UA/RU320/1440 cases and refreshed those captures.
- Compact process: 12 UA/RU cases passed at320,390,701,768,980,1440px for actual visible icon/title pair and paragraph centering, no wrap/overlap/overflow, aligned tablet/desktop paragraph starts, selected icon retained, and reduced height.
- Mobile connector follow-up: eight UA/RU320,390,700,701 cases passed line containment, center/color, preserved compact layout, breakpoint, no trailing line, and no overflow/errors.
- Scoped `git diff --check`: passed.

Browser checks allowed local GET requests and blocked all external requests and POST requests. The static roads/coast image rendered locally; the full dialog retained its exact vector fallback when external tiles were blocked. Root owns final build/package and independent actual-WordPress checks.

Final visual review captures: `work/refinement-1.8/landscape-map/panel-uk-1440.png`, `panel-ru-1440.png`, `panel-uk-320.png`, and `panel-ru-320.png`. Pre-adjustment captures are preserved in `pre-legibility/`; older process/connector evidence remains in its corresponding working folders. Sources are frozen for root build.

## Resolved P2: narrow-map attribution overlap

Root's final visual review found that the bottom-right attribution overlay could cover the southern red-zone tip at 320px. At 900px and below the attribution now occupies its own static row after the actual map viewport, with 6px×10px padding and the map-frame background. Desktop attribution remains unchanged. The 640×440 map viewport, source image, zone paths, labels, and geographic scale were not changed or reduced.

`python3 work/refinement-1.8/landscape-map/copyright-check.py` passed all eight UA/RU cases at320,390,700,768px. The credit begins at or below the map viewport bottom and clears the southern tip; base/overlay remain aligned at the original aspect ratio with no crop, overflow, or page errors. Scoped diff check passed. Reviewed fresh captures in `work/refinement-1.8/landscape-map/copyright-fix/`, including `map-ru-320.png` and `map-uk-768.png`; full panels and measured results are preserved alongside them. This CSS-only fix is frozen for root's new build.
