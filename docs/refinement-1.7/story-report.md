# Story, delivery preview and DualSense refinement — 1.7

## Changed files

- `src/sections/Story.tsx`
- `src/story-refinement.css` (new; imported last from Story)
- `wordpress/joyrent/assets/images/delivery-zone-preview.svg` (new; 6.2KB)

No map component/button, booking, footer, global styles, package or backend files were edited by this agent.

## Result

The three outlined process boxes are replaced by an open ordered sequence with a thin connecting rail and small calendar/truck/return markers. Desktop uses three columns; mobile uses one vertical sequence. The return marker avoids introducing another controller icon while the user chooses the booking icon. Existing request/confirmation/return workflow remains explicit.

The delivery block aligns content at the top. Desktop has a full-width heading above a 60% text / 40% geographic preview row. The approximately 230px preview preserves all six approved GeoJSON polygons, adds a very faint cartographic grid, and locates the geographic Odesa point at longitude 30.7233 / latitude 46.4825. The city label is Ukrainian or Russian in HTML. Below 900px the delivery columns stack; mobile retains the smaller preview beside the heading and puts the paragraph across the full width. No extra metric, invented district, operator marker or new map control was added. The interactive map component and its props remain unchanged and lazy.

DualSense uses the existing original transparent image and responsive variants, with a 650px desktop maximum and a 430px mobile maximum. The caption is removed. A 10.8-second CSS cycle produces controlled yaw of approximately ±5 degrees, small roll and a soft masked light passing across the product. It adds no vertical float or scaling/cropping. Both movement and light pause while offscreen or while the document is hidden. Reduced motion presents the neutral original image with the light layer removed. The original image remains RGBA 1536×1024, SHA256 `24c1bc030b6a5ece21323962b9d76ac2dc0434f533d0cb1f6ea968ae350b0cff`.

The kit line uses 1–2 controllers to reflect the existing optional second controller, with the second free. The note confirms selected games before rental instead of promising preparation of unavailable games. Delivery wording says delivery and return are included in the stated price; configured zone fees remain in the fee list.

## Validation

- Watched the motion behavioral check fail on the previous implementation because movement continued while the document was hidden.
- `npx tsc --noEmit`: passed after the final source changes.
- Full `npm test`: 46/46 passed in 4 files, including current copy, geometry, rental and draft suites.
- Browser motion behavior: changing yaw/roll and moving light while visible; movement/light freeze when hidden; resume on return; pause offscreen; immediate neutral image for reduced motion; no caption.
- Eight responsive browser cases: UA/RU at 320, 390, 980 plus UA at 1440, 1920. No document/delivery horizontal overflow or page exceptions, no process-card borders, correct localized preview alt text, and retained lazy map loading, six real map polygons and Escape/opener focus restoration.
- Controller image widths in reduced motion: 280px at 320, 342px at 390, 482.375px at 980, 650px at 1440/1920. All products remain fully visible.
- SVG contains six direct geographic paths plus a separate faint grid and one genuine city point. It is generated from the approved bundled geometry with Mercator projection, retaining all source vertices in the preview paths.

These are isolated Story harness checks, distinct from final packaged-page acceptance. Safe receipts: [layout results](evidence/story-layout-results.json) and [motion results](evidence/story-motion-results.txt). Final release screenshots and packaged-page checks are linked in [README](README.md). Reproducible local scripts remain in ignored `work/fixes-1.7/story-qa/`.
