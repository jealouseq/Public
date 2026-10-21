# JOYRENT 1.7 independent review

Base: `9ea2210`. Reviewed the final pending source changes after Story and backend implementers declared ready, including new CSS/copy/page-seed/preview files and the targeted evidence. This pass changed no production files, committed nothing, ran no state-mutating WordPress fixtures, and sent no POSTs or mail. The report is the only saved review deliverable.

**Final spec verdict: approved for the reviewed refinement scope. Code-quality verdict: approved. No unresolved Critical, Important or Minor findings.**

## Important finding resolved during review

The first page-copy migration unconditionally replaced previous neutral defaults with fixed deposits, free second-controller terms, zone prices and a seven-day threshold, even when owner business settings differed. This introduced native-page contradictions while preserving those settings.

Resolved in `wordpress/joyrent-rentals/includes/store.php:150`–172: a shared guard now checks city/translations, both deposits, controller conditions, unknown short-delivery fee, both zone fees and free threshold before either creating or updating price-bearing FAQ/terms pages. Divergent conditions use the archived neutral copy. Privacy updates remain independent. The same guard covers missing pages, preventing contradictory new defaults for a custom-configured shop. The final short-fee-only case is included.

Existing-page updates remain limited to exact published 1.6 defaults with matching title/content and an empty excerpt. Custom titles, content, excerpts and draft pages remain intact, with their IDs and metadata retained. The archived legal strings and both generated FAQ bodies were independently compared with HEAD 1.6 and match. The 1.7 path does not rerun the earlier game/settings migrations; no order or private-recipient migration was added.

Evidence inspected: `tests/php/copy-migration.php` and `work/fixes-1.7/copy-business-green.json` report **42 checks, zero failures, zero mail attempts**, including approved defaults, custom pages, custom deposits/controllers/fees/threshold/city, missing pages, isolated short delivery fee, settings/private-key preservation and unchanged game/order IDs. Independently invoked the actual private `seed_page` method with in-memory WordPress/settings stubs: **15 checks passed** for approved/custom existing and missing terms plus independent privacy migration. No database was loaded for that proof.

## Frontend and performance review

- The supplied 320px RU hero/footer and 390px UA kit/process screenshots retain the approved black/white/amber composition. The compact rounded hero action and quiet footer phone/Telegram row fit the narrow viewport; telephone targets retain the international number and visible shorthand has an accessible full label. The mobile map opener is larger and centered.
- The connected process remains an ordered three-step sequence with manual confirmation and return. The original DualSense photo and responsive variants are preserved, with a bounded image width and no scale/crop. The caption is removed. A modest yaw/roll cycle and travelling masked light share visible/offscreen/hidden-document state; reduced motion removes animation and restores the neutral original image. Cleanup disconnects observers/listeners.
- Full GeoJSON and Leaflet remain dynamic imports inside the opened-dialog effect. The new preview is a separate approximately 6.2KB SVG with native lazy loading; it adds no runtime geometry dependency. Independently verified all **219 original vertices** against the six preview paths under Mercator projection, with less than 0.001 SVG pixel error, plus the exact Odesa marker position. The city label is localized outside the image.
- Inspected Story's eight responsive cases (UA/RU 320/390/980 and UA 1440/1920), native-map focus/geometry checks and motion evidence. They report no overflow or page exceptions, no eager map/geometry fetches, six polygons after opening, Escape/opener focus restoration, and movement/light freezing when hidden, offscreen and under reduced motion.
- UA/RU changes retain rental prices and pending address-dependent delivery, qualify included green/yellow delivery, explain the deposit separately, correct counted labels and avoid duplicating an existing explicit internet requirement. REST changes alter validation wording, with matching RU translations; normalized request data and fingerprints are unchanged. Controller SVG options remain a user choice and no replacement is required for approval.

## Independent verification and limits

`npx tsc --noEmit`, the four targeted copy tests, `git diff --check 9ea2210`, and syntax checks for all five changed PHP files passed. Rechecked final `store.php` syntax after the business guard correction. Story's report records the complete 46-test frontend suite passing; that broader suite and state-mutating backend fixtures were inspected, not repeated by this reviewer.

Full fresh-build browser acceptance and release packaging remain root-owned. This review does not claim physical Safari validation or actual mailbox delivery. The inherited static Kit free-controller line predates 1.7 and was kept within the approved production conditions; it is separate from the newly introduced native-page contradiction, which is resolved.

## Release integration closure

After root rebuilt the final ZIPs with the complete business guard, independently reopened `releases/joyrent-rentals-1.7.0.zip` and compared its `joyrent-rentals/includes/store.php` with the current reviewed source: **byte-identical**. The earlier stale-ZIP observation is closed. Root's `work/refinement-1.7/package-verification.json` additionally records runtime-file/source equality across all release ZIPs and 17 PHP syntax checks with zero failures; that broader packaging receipt was inspected rather than independently repeated in this narrow closure pass.

## Final native footer and title follow-up

Approved the subsequent `wordpress/joyrent/footer.php` contact-row fix. It reads only `JR_Settings::public()`, escapes text/attributes/URLs, normalizes telephone targets, supplies full accessible phone/Telegram labels, and uses `noopener noreferrer` for the external Telegram link. It writes no settings, private recipient data or orders, adds no JavaScript, and stays excluded from the React front page to avoid duplicate footers.

Independent Chromium checks at **320px on all six native FAQ/terms/privacy routes** now pass: localized page language and return/FAQ links, the approved phone/Telegram destinations, contact bounds inside the viewport, phone target height 44px, Telegram target 44×44px, HTTP 200, and no page exceptions or horizontal overflow.

The first narrow check also exposed existing native privacy-title overflow in the unchanged 1.6 `index.php` template. Root resolved it with the established Onest service font, responsive 24–48px title sizing and long-word wrapping, plus the content wrap guard. The final UA/RU privacy headings fit their 280px content area and both pages have document width 320px. No stored page content changes were introduced by that template correction.

Independently linted both final PHP templates and compared `joyrent/footer.php` and `joyrent/index.php` inside the current theme ZIP with source: both **byte-identical**. Footer SHA256: `761b5601dfd0d003a18ddbd00e04b8a8bd5998df851887c33e79e0a04bca748d`; index SHA256: `880b849466d366061f9bcfef2ca4a25c9ab2be672ece9e7cadb4b053b077d35f`. No unresolved finding remains from this follow-up.
