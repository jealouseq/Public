# JOYRENT 1.5 — Product Design QA

Source visual truth: the user's desktop screenshots and explicit refinement requests: remove the final CTA, make delivery clearer, integrate the free-delivery notice into tariffs, replace tariff arrows and controller icon, expand/reorder the catalogue and restore restrained hover, animate the two-line hero headline. The user specifically rejected straight arrows and permitted removing obsolete game records from this test store. The previous 1.4.2 release is the controlled baseline, not a target whose rejected layout must be reproduced.

Baseline captures: `docs/refinement-1.5/before-delivery-{390,1440}.png`, `before-rates-{390,1440}.png`, `before-games-{390,1440}.png`, `before-final-cta-{390,1440}.png`. Implementation: `delivery-panel-{390,1440}-uk.png`, `rates-ps5-{390,1440}-uk.png`, `games-{390,1440}-uk.png`, and `page-end-1440.png` in the same folder. Both release versions use anonymous local WordPress/WooCommerce at localhost:8080, UA, DPR1, 390×1700 or 1440×1700 CSSpx, default PS5/3days/one controller. Regions start at the relevant component. Image comparisons retain native capture scale; component heights differ intentionally. The delivery panel's desktop region grows from 1290px to 1292px because the new panel includes a border. No pixel equality to the unknown viewport/density of user screenshots is claimed.

Final screenshots also cover UA/RU at 320, 390 and 1440px, touch mobile contexts, navigation, PS4 tariffs, booking and dialogs. First-screen captures include navigation at 390×844 and 1440×1000; component screenshots exclude browser chrome. Native date-input presentation follows the browser/OS and is not a claim of iOS rendering.

## Findings resolved

- **P2 — repeated final CTA.** Removed the requested CTA section and its unused styles. The form now leads directly into the footer. Evidence: before-final-cta captures and `page-end-1440.png`; absence asserted at all 32 layouts.
- **P2 — delivery hierarchy.** The old strip put title, address instructions and free-delivery terms into three shallow columns. The new panel separates receiving/address from delivery/connection. The phone stacks these with a separator and 24px padding. Matched comparisons and the full process section were inspected. Configured delivery text and service-zone qualification remain present.
- **P2 — tariff action and notice.** Each tariff now has a 48px-high outline button, a thin chevron in a quiet circle and warm hover border. No straight arrow is used in tariff choices or their conditions link. A small delivery badge sits under eligible tariffs, with reserved space for alignment. The full-width notice is removed; one link leads to the delivery panel. PC/phone PS5 and PS4 captures confirm centered mobile controls, a centered third PS4 tariff, readable amounts and no overflow.
- **P2 — game relevance and editions.** Catalogue grows from 12 to 20. FC27/UFC6/BO7 replace older editions with official artwork; S.T.A.L.K.E.R.2 and sports/fighting/co-op choices receive priority. Release/platform checks are in `catalog-research.md`. Priority is editorial, informed by Ukrainian retail context, not a claimed national sales ranking. PS4 compatibility is filtered; MK11 remains a PS4 option. Priorities remain editable after the one-time migration.
- **P2 — motion feedback.** Hero uses a brief word entrance inspired by the supplied 21st.dev Blurred Stagger Text. A pre-existing direct-child CSS rule initially broke the second phrase into a third line after the markup change; the targeted rule was corrected and the complete width/language matrix rerun. Hover lifts the whole game card by 5px and highlights its control; covers never zoom. Actual transforms, dimensions and reduced-motion states were measured.
- **P2 — controller icon.** Replaced the rejected Iconoir outline with one original JOYRENT SVG, shared by booking, rental steps and missing-cover fallback. Broad touchpad, symmetric sticks, shoulder tabs and curved grips retain the light outline style. Native and explicitly enlarged crops were inspected. Product photo and its soft animation/light are unchanged.

No actionable P0/P1/P2 remains in the verified scope. These findings describe requested changes and the corrected temporary heading regression, not unresolved defects.

## Five visual surfaces

- **Typography:** Unbounded 550 and local Manrope remain. Hero has exactly two semantic lines at every checked width/language; accessible H1 retains the complete phrase. Word blur resolves to zero, opacity to one, reduced motion exposes text immediately. Tariff titles, numbers and currency fit at 320px; delivery has a clear heading/body hierarchy.
- **Spacing/layout:** Hero actions/price and mobile tariff switch remain centered. Button rows align on desktop; delivery badges reserve height. Process cards retain their layout, with 32px separation before the panel. Mobile panel has padded sections and a horizontal separator. Booking dates remain full-width separate rows on phones. Footer follows booking after CTA removal.
- **Colors/tokens:** Near-black, warm-white, grey and muted amber remain. Delivery border and tariff hover use restrained warm tones. No bright gradients or counters were added. Keyboard focus remains visible.
- **Images/assets:** Existing complete studio PS5 scene and transparent DualSense are preserved. Current game covers are official 720×720 WebP artwork. Eleven new images were visually checked against exact titles/editions. Hover and opening preserve cover dimensions. Controller SVG is vector; the 4× detail crop is an inspection enlargement, not a native-resolution screenshot.
- **Copy/content:** UA/RU sections match in purpose. Seven supplied rental prices are unchanged. Delivery retains service-zone qualification; unknown short-delivery/deposit amounts are agreed with the manager. Game selection requests availability. No invented popularity figures/countdowns. Obsolete test-game posts are removed per instruction; existing prices/settings/orders and other authored games remain intact.

## Evidence reviewed

Matched native-scale delivery, tariff and game comparisons were opened together with final implementation captures. Focused native/detail controller crops, actual browser entrance frames, game hover, centered S.T.A.L.K.E.R.2 dialog, phone first screen, RU menu and page ending were inspected.

See `docs/refinement-1.5/README.md` for links. `hero-text-reveal.gif` contains actual Chromium frames; only the viewing GIF loops. On the site entrance runs once. Controller photo/light is unchanged and its existing actual motion checks still pass.

## Verification

- `npm test`: 16/16; TypeScript/Vite/package succeeds. PHP syntax passes all 16 theme/plugin files; PHP domain suite passes 15 checks.
- `refinement-layout.py`: 32 UA/RU cases at 320/360/375/390/430/700/768/900/901/980/1024/1280/1440/1920/2560/3355px, 224 tariff selections. Two headline lines, correct amounts/durations, no overflow/JS errors, two eligible delivery badges per console, no removed sections.
- `artwork-motion.py`: word entrance/final state, hover −5px, invariant cover dimensions/transform, immediate reduced motion. All supported covers/dialogs at 390/1440 in UA/RU, square uncropped artwork and restored opener focus.
- Existing `browser.py`: 11 widths, console/date safety, carousel/filter/dialog/focus, consent, actual local WooCommerce request and immutable confirmation, mobile menu, no failed assets/errors. `polish-browser.py`: bilingual form state, localized errors, same-ID retry, actual controller motion/light and reduced motion.
- `dialog-layout.py`: six sizes including short screen, centered dialogs, preserved cover width, resize and reachable controls. Historical 1.2 evidence is preserved; future captures go into ignored work/.
- `catalog-upgrade.php`: 14 migration assertions: missing-game import, no duplicate/republication of drafts/trash, removal of obsolete IDs, preserved owner text/thumbnail, PS4-specific MK11, unchanged tariffs/settings/order IDs, repeat upgrade and intentionally hidden catalogue.
- REST request with all eleven new game IDs creates a local request at the server's 2500 UAH seven-day price; retry returns the same reference. Existing requests/bilingual integration tests pass.
- All three 1.5.0 ZIPs pass integrity/content/version checks. No new npm dependencies. Request/checkout engine is not replaced.

## Limits and delivery

Current verification is on local WordPress. flowers-luxury.shop is not deployed by this update; install both 1.5.0 ZIPs and clear cache. Actual Safari/iPhone rendering remains untested because WebKit download is blocked; Chromium touch emulation is not Safari. No production-host performance or national popularity statistics are claimed.

Follow-up visual polish: no P3 change required within this scope.

final result: passed
