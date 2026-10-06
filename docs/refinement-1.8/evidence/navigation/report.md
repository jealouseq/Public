# JOYRENT 1.8 — «Як отримати консоль» navigation

The tariff-section link was pointing to `#delivery`, a panel below the three rental steps. Clicking it therefore skipped the process heading and every step. This is reproducible on the currently installed live 1.7 site and the local source in Ukrainian/Russian, at 390×844 and 1440×1000.

## Bounded fix

Changed only the link `href` in `src/sections/Tariffs.tsx` from `#delivery` to `#how-it-works`. This is the actual process-section ID and the same destination used by the working Header “How to rent” link. The Story owner confirmed the ID will remain stable during the separate 1.8 composition work. No custom scroll handler, router behavior or additional styling was added.

The page already uses `scroll-padding-top: 105px` on desktop and `25px` on mobile. The measured Header is `position: relative`, not sticky. After clicking the corrected link, the process heading is fully visible at approximately y=245px on desktop and y=117px on mobile, with all three current step titles visible. No offset correction was needed. The RU query `?lang=ru` stays intact.

## Actual-click evidence

| Context | UA mobile | UA desktop | RU mobile | RU desktop |
|---|---|---|---|---|
| Installed live 1.7 | Wrong `#delivery` | Wrong `#delivery` | Wrong `#delivery` | Wrong `#delivery` |
| Local source before | Wrong `#delivery` | Wrong `#delivery` | Wrong `#delivery` | Wrong `#delivery` |
| Local source after | Correct `#how-it-works` | Correct `#how-it-works` | Correct `#how-it-works` | Correct `#how-it-works` |

Before the fix, the process heading was around y=−479px on mobile and y=−199px on desktop; no process-step title was visible. The link was not landing at booking or losing language: its incorrect hash was the root cause.

- [Live receipt](live-receipt.json): four actual clicks on https://flowers-luxury.shop/, served `main-CNh65H_Y.js`, current 1.7. The deployed site was read-only and retains the old target until a release is installed.
- [Local before](before-local-receipt.json) / [local after](after-local-receipt.json): four actual clicks each on the source Vite server at localhost:5173. After: 4 PASS / 0 FAIL, correct hash and fully visible process heading, no overflow.
- [PNG manifest](screens-manifest.json): twelve exact viewport screenshots, with dimensions and SHA256. `live-{ua,ru}-{mobile,desktop}.png`, `before-local-*.png`, `after-local-*.png`.

All twelve contexts had zero POST attempts and zero page exceptions. Every non-GET/HEAD request was intercepted before transmission; external GETs were blocked. No contact details were entered, no order/email was created and no live files/settings were changed. `git diff --check` passed. No package or commit was created by this worker.

Screenshots show the process/delivery composition at the time of this bounded check. The separately owned centered-step/map refinement is still ongoing and receives its own final QA.
