# JOYRENT 1.7 — authoritative focused browser acceptance

**20 UI cases PASS + 6 native-page cases PASS: 26 current PASS / 0 current FAIL.** Checks used packaged WordPress at http://localhost:8080, entry **main-CNh65H_Y.js**, CSS **main-hpKQBRcV.css**. These are new1.7 focused cases; no1.6 totals are reused.

Twelve viewport cases are retained from immutable run20261003T103549Z. Eight motion/art cases were rechecked in20261003T104416Z after diagnostic corrections; six native FAQ/legal cases were rechecked after root's PHP footer fix. Four native passes are retained from20261003T104841Z, with only the two privacy routes retested after the native title fix. Every result carries observedRun and immutableSource in [browser-report.json](browser-report.json). All prior raw observations remain intact.

## Verified behavior

- UA/RU at **320/390/768/980/1440/1920**: hero is exactly two unclipped lines. Mobile actions/price center within the content column. Public footer phone/Telegram are visible,44px targets, in the same row without overlap. The full phone remains at390; only widths<=375 shorten it.
- The process forms one connected sequence with no outlined cards or copy overflow. The kit shows the complete centered controller at its native aspect ratio and has no caption.
- The larger50/52px map opener centers on mobile. All12 viewports open/reopen the native dialog, render six local polygons, preserve the original main node, load the entry once, keep Leaflet/geometry lazy, and restore opener focus after Escape/Close.
- Normal motion at390/1440 in both languages changes transform while onscreen, pauses offscreen/document-hidden, immediately neutralizes after a live reduced-motion change and resumes afterward. CSS pause stability is sampled after2rAF. Hidden-document events are simulated because headless Chromium reports all tabs visible.
- Game hover preserves image source/transform. Three detail dialogs per language/width retain the original square images and source-file SHA256, native-dialog focus return and singular player copy. Actual BO7 legacy copy correctly receives an internet note; a browser-only catalog GET fixture confirms the exact known sentence suppresses a duplicate.
- Controller singular/plural forms, free second controller, green/yellow seven-day qualification, red taxi rates and both-direction fees are correct. Six native UA/RU FAQ/privacy/terms routes expose the updated managed copy, deposits, password/PSN cautions and internet requirements for some single-player games. Each native route additionally checks footer contacts at320px,44px targets, same row, bounds/non-occlusion and no horizontal overflow.

## Assets and screenshots

Served assets returned200 and match local final build bytes:

| Asset | Bytes | SHA256 |
| --- | ---: | --- |
| main-CNh65H_Y.js | 485194 | 99b37d873bfc96b85b555b7255a2aca4d7e755d46f4137756d1c94d9eac25b08 |
| main-hpKQBRcV.css | 92276 | 3a577b6042e4db7b60caebb9ebed5e1a53d222d18eff2a27e42967fa86ac5fdd |

**12 actual viewport screenshots** were captured after fonts and visible images loaded/decoded, with incidental focus blurred and accessibility CSS unchanged. Copies are in docs/refinement-1.7/screens. [browser-selected-screens.json](browser-selected-screens.json) records source/destination paths, dimensions, hashes and actual entry URLs. Map images show the **local-vector scheme; external OSM raster GETs were intentionally blocked**. Live1.6 baseline remains separate under live-baseline/.

## Local/live saved copy

Fresh live catalog GET at **2026-10-03 10:40:07 UTC** uses concise service text: delivery, connection, collection, then address/time confirmation. The local saved description repeats200/300/taxi fees. Local screenshots reflect that persisted setting; QA changed neither setting. [browser-delivery-copy-comparison.json](browser-delivery-copy-comparison.json) preserves both languages and fetch provenance. Bundled defaults/API fallback already use concise copy.

## Safety and retained history

All POST/other non-GET/HEAD requests were guarded before network continuation. Acceptance transmitted only local GET/HEAD; raster tiles were blocked. Accepted26 cases have **zero POST attempts, zero page exceptions and zero first-party HTTP failures**. Expected blocked-tile console entries are retained. No contact details were entered, no real order/email was sent and no product code was edited by QA.

The first partial run retained11 harness errors (phone format/cap-location expectations). The first completed UI run retained seven harness observations: three immediate pause samples caught the last compositor frame (diagnostics proved one16.6ms advance then stable after2rAF), and four BO7 assertions ignored the actual stored custom description.

**Six native contact failures and two privacy overflow failures were real product findings**, not harness errors. Native FAQ/legal footers lacked public phone/Telegram while privacy copy directed customers to those channels. Root added configured capsules to footer.php; subsequent320px checks exposed two long-title overflows. Root changed index.php titles to the established service font with wrapping. The six scoped native cases now pass, including320px geometry; only the two affected privacy routes were repeated after the title change. Failure screenshots and raw traces remain unchanged.

| Raw run | PASS | FAIL observations | Scope |
| --- | ---: | ---: | --- |
| 20261003T103342Z | 0 | 11 | interrupted partial |
| 20261003T103549Z | 13 | 7 | all |
| 20261003T104416Z | 8 | 6 | final |
| 20261003T104841Z | 4 | 2 | native |
| 20261003T105301Z | 2 | 0 | native |

## Current case provenance

| Case | Current result | Observed run |
| --- | --- | --- |
| art-ru-1440 | PASS | 20261003T104416Z |
| art-ru-390 | PASS | 20261003T104416Z |
| art-uk-1440 | PASS | 20261003T104416Z |
| art-uk-390 | PASS | 20261003T104416Z |
| motion-ru-1440 | PASS | 20261003T104416Z |
| motion-ru-390 | PASS | 20261003T104416Z |
| motion-uk-1440 | PASS | 20261003T104416Z |
| motion-uk-390 | PASS | 20261003T104416Z |
| native-faq-ru | PASS | 20261003T104841Z |
| native-faq-uk | PASS | 20261003T104841Z |
| native-privacy-ru | PASS | 20261003T105301Z |
| native-privacy-uk | PASS | 20261003T105301Z |
| native-terms-ru | PASS | 20261003T104841Z |
| native-terms-uk | PASS | 20261003T104841Z |
| viewport-ru-1440 | PASS | 20261003T103549Z |
| viewport-ru-1920 | PASS | 20261003T103549Z |
| viewport-ru-320 | PASS | 20261003T103549Z |
| viewport-ru-390 | PASS | 20261003T103549Z |
| viewport-ru-768 | PASS | 20261003T103549Z |
| viewport-ru-980 | PASS | 20261003T103549Z |
| viewport-uk-1440 | PASS | 20261003T103549Z |
| viewport-uk-1920 | PASS | 20261003T103549Z |
| viewport-uk-320 | PASS | 20261003T103549Z |
| viewport-uk-390 | PASS | 20261003T103549Z |
| viewport-uk-768 | PASS | 20261003T103549Z |
| viewport-uk-980 | PASS | 20261003T103549Z |
