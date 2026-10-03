# JOYRENT delivery and process refinement

Goal: make delivery zones understandable, center the rental sequence, and present generated controller icon choices in the established black/white/amber style.

Structure: delivery header and desktop map action, a landscape 640×440 real OSM road/coast basemap with exact six-zone overlay beside compact fees and a warm full free-delivery callout. At 900px and below the preview stacks above fees; phones retain the existing centered 52px map action after them. Each rental step puts its icon beside the title, centers that whole pair, and centers the paragraph below. Desktop has short connectors; mobile has an 18px warm vertical connector in the 26px inter-step gap.

- [x] Update `src/sections/Story.tsx`, `src/story-refinement.css` and optimized landscape static geographic preview. Preserve six Funbox polygons / 219 original vertices, saved owner copy, fees and lazy interactive map; include real OSM roads/coastline, visible attribution and reusable source/license/manifest.
- [x] Generate three independent transparent DualSense icon designs. User initially chose option 2, then explicitly selected the attached detailed contour (displayed option 1). Adapt that final image to the shared SVG. Final optical stroke correction and 28px Story/Booking usage resolve the recorded visual P2.
- [x] Verify UA/RU at 320, 390, 768, 980, 1440 and 1920px, whole icon/title-pair midpoint, paragraph centering, mobile connectors, exact basemap/zone alignment and callout, overflow, dialog keyboard lifecycle and lazy loading. Final run `20261003T125119Z`: 12 fresh cases pass on `main-DymdWJeq.js` / `main-CBL0otFI.css`, including 700/701/900/901 boundary subchecks. Final TypeScript/build and 46 tests pass; both recorded visual P2 findings are resolved.
- [x] Package theme/preview 1.8.0 with ODbL source/license/manifest, retain existing unchanged plugin 1.7.0 and historical ZIPs, document installation and actual verification evidence. Files match source; all four earlier iterations remain archived.

Constraints: no private email in public assets; no orders or mail sent; no fabricated map geography or business terms; no new eager mapping dependencies. User explicitly requests planning followed by implementation, so bounded layout changes proceed without a separate approval gate.
