# JOYRENT 1.5 hero image audit

Read-only audit of branch `feat/joyrent-wordpress`, HEAD `ed3145f`, on 2026-10-03. No application code, assets, release ZIPs, or live-site state changed. Fresh anonymous local WordPress capture in Chromium at `http://localhost:8080/`, with reduced motion, 1000 CSSpx viewport height, and only localhost requests permitted. Seven widths × DPR1/2/3 (21 cases). This report concerns the PS5 hero scene only.

## Answer: newly generated desktop framing plus proportional display enlargement

- **1.4 (`f55f974`)** introduced two separate ImageGen studio masters, desktop and mobile, both 1448×1086. Repo source: `docs/refinement-1.4/README.md:21` and`:25`. The mobile scene is independently composed rather than a crop of the desktop.
- **1.4.1 (`fdd2080`)** introduced a new native 1448×1086 desktop closer-frame image, `ps5-commercial-desktop-close.png`. It is recorded as an ImageGen edit, retaining the black studio/amber light/matte floor. The source console occupies more of the frame and its perspective/details change slightly. Desktop `picture` additionally became 110% of its reserved figure. Mobile retained the previous master and became 112% of its figure. Sources: `docs/refinement-1.4.1/README.md:3`,`:17`; `docs/refinement-1.4.1/design-qa.md:7` and the `fdd2080` diff.
- **1.4.2 (`9d9f312`) and 1.5 (`ed3145f`)** use exactly the same scene images and `hero-media.json` as 1.4.1, proven by byte-identical SHA-256 hashes in `asset-history-hashes.json`. Thus1.5 did not generate or stretch a new PS5 scene.
- Current CSS displays the entire image at 4:3, `width:100%;height:auto;object-fit:contain;filter:none;transform:none` on the actual `img`. Every tested box preserves4:3 (subject only to subpixel rounding). `translateY(-50%)` positions the parent `picture`; it does not stretch the raster. Source: `src/styles.css:660`–`:662`,`:683`–`:684`.

The git documentation supplies generation provenance; original ImageGen request/response logs are not part of this repository. Measurements independently establish the current files, their history and rendering.

## Native assets and compression

| Current served image | Actual decoded pixels | Bytes | Purpose |
|---|---:|---:|---|
| desktop-close-768.webp |768×576|23,294|DPR1 desktop|
| desktop-close.webp |1448×1086|83,212|DPR2/3 desktop|
| mobile-560.webp |560×420|11,462|DPR1 mobile|
| mobile.webp |1120×840|48,246|DPR2/3 mobile|
| desktop-close.png master |1448×1086|1,438,661|Source, not requested by page|
| mobile.png master |1448×1086|1,373,759|Source, not requested by page|

All served files are lossy VP8 WebP, three channels, opaque. Desktop full-resolution WebP exactly reproduces using Sharp `.webp({quality:92,effort:6})` directly from its PNG, with no resizing. Mobile1120px WebP exactly reproduces from its PNG via `.resize({width:1120,withoutEnlargement:true}).webp({quality:92,effort:6})`. Both small 768px/560px WebP derivatives reproduce at quality 89/effort 6. See `encoder-reproduction.json`. The generic `scripts/optimize-assets.mjs:12` uses quality86/effort5; it is not a faithful record of the custom hero derivative encoding.

## Fresh rendered sizes and true density

| Viewport width | Scene CSSpx | DPR1 selected source | DPR2/3 selected source | Full source pixels/CSSpx | DPR3 source coverage |
|---:|---:|---:|---:|---:|---:|
|320|313.59×235.19|560|1120|3.572|119.1%|
|390|383.03×287.27|560|1120|2.924|97.5%|
|900|492.80×369.59|560|1120|2.273|75.8%|
|901|409.34×307.00|768|1448|3.537|117.9%|
|1440|658.42×493.81|768|1448|2.199|73.3%|
|1920|701.19×525.89|768|1448|2.065|68.8%|
|3355|701.19×525.89|768|1448|2.065|68.8%|

DPR1 and DPR2 are supplied with sufficient pixels in all 21 measurements. AtDPR3 desktop 1440, the browser must enlarge 1448px to 1975 devicepx (36.4% linear enlargement); at the widest desktop scene it must enlarge to 2104 devicepx (45.3%). Mobile390 has only a 2.6% linear shortfall; the maximum mobile scene has a 32.0% enlargement. This is a real source-resolution limit even though CSS proportions stay correct.

`naturalWidth` reported by a browser for `w` srcset is density-corrected and is approximately the `sizes` slot width; it is **not** the decoded file's pixel width. This report uses Sharp decoded dimensions to calculate density. Full data: `render-measurements.json`, `measurements.csv`, `asset-metadata.json`.

## Source selection, preload and blending

- React picture: `src/sections/Hero.tsx:22`–`:25`, imports the shared manifest at`:6`. Mobile source applies through 900px. Eager/high priority image has explicit aspect ratio dimensions.
- Shared source config: `wordpress/joyrent/assets/images/hero-media.json:2`. Desktop 768/1448w with `sizes="(min-width:1536px)702px,47.3vw"`; mobile 560/1120w with `sizes` matching112% image width.
- PHP preloads: `wordpress/joyrent/functions.php:50`–`:58`, same media/srcset/sizes. Each of the 21 cases issued exactly one hero image request, initiated by the matching image preload. No PNG master, inactive art direction or unnecessary duplicate image was fetched.
- Desktop `sizes` slightly overestimates actual layout (e.g.681px slot vs658px rendered at 1440), favoring resolution; it does not cause lower-quality selection. Above 1536 it matches the capped 701px canvas closely.
- Figure uses `mix-blend-mode:lighten`. Picture masks fade only image margins (left0–8%, right96–100%, top0–2%, floor from 92% desktop/90% mobile). The PS5 and controller bodies are in the fully opaque central area. The fade explains the deliberately soft floor/background transition; it does not blur product detail. Actual image computed `filter` is `none` in every case.

## Visual findings

Fresh captures inspected: `hero-1440-dpr1.png`, `hero-390-dpr2.png`, `rendered-scene-1440-dpr1.png`, `rendered-scene-1440-dpr3.png`, `rendered-scene-390-dpr3.png`. Current native masters were opened directly with original detail. Exact console/controller crops from master and WebP were also inspected.

The black studio, warm amber glow, matte textured floor and complete silhouettes are intact. No nonuniform stretching or strong square compression blocks are visible in these current local renders. Fine controller markings, white-shell surface shading and floor texture are already softened/simplified in the original generated PNG. Compression removes some small surface detail and smooths shading further, visible in equal native crops; it is not the sole source of the softness. Browser enlargement above native pixel density further softens edges and small controls at DPR3. The console's large external edges remain clean at ordinary viewing size.

**Actionable image-quality issue:** existing srcset stops at 1448px desktop and 1120px mobile, so large DPR3 views lack native pixels. Preserve the present studio composition/layout; for mobile, a 1448px derivative from the existing master would almost cover the maximum DPR3 case without regenerating anything. Full DPR3 desktop detail needs a true source of at least 2104px width at the maximum rendered size, or a smaller displayed canvas. Artificially increasing file dimensions alone cannot add reliable native detail. These are recommendations only; nothing was modified.

## Evidence limits

The assets were subsequently verified against the deployed public URLs: all four WebP files are byte-identical to these local sources (see addendum). DPR/rendered-geometry measurements are local; the live browser's currentSrc, zoom and DPR still govern the pixels actually displayed. Chromium touch emulation is not a physical phone or Safari test. Page zoom, display scaling and enlarged chat screenshot previews can exaggerate apparent pixelation. Generation provenance is documented in git; exact original prompt/model parameters are unavailable here.


## Deployed-asset verification addendum

On 2026-10-03, read-only HTTPS requests through the user-authorized connected device returned all four deployed WebPs with HTTP 200 and Content-Type `image/webp`. Python3 `urllib.request` retained TLS certificate validation and hashed raw response bytes in memory; the image-content tool was not used, so tool transcoding cannot affect this comparison.

All four live SHA-256 hashes, byte counts and native pixel dimensions exactly match the local 1.5 sources in the table above. Every live response has Last-Modified `Sat, 03 Oct 2026 02:47:43 GMT`. The live files therefore have the same scene, resolution and compression as the audit evidence; there is no difference introduced by compression or resizing in the deployed asset files. Full results: `deployed-asset-hashes.json`.


## Delivered evidence

Machine-readable measurements and hashes are in [evidence/](evidence/). Native PNG/WebP crops are in [screens/](screens/). The main Russian audit explains the findings and density limits.
