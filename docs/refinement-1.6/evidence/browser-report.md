# JOYRENT 1.6 independent browser acceptance

Current per-case evidence against `http://localhost:8080`: **76 passed; 0 failed.**

All POST intercepted before transmission. Only local GET/HEAD allowed. Synthetic contacts only.

This is Chromium emulation, not physical iPhone/Safari. API success responses in form tests are synthetic interception responses, not saved orders or proof of mail delivery.

Each case below uses its latest observation. A narrow retest replaces only those cases; the full baseline remains linked to its original run.

| Run | Scope | Pass | Fail |
|---|---|---:|---:|
| `20261003T083751Z` | all | 44 | 2 |
| `20261003T084814Z` | functional / pricing-mobile-bar | 2 | 0 |
| `20261003T085936Z` | final | 4 | 14 |
| `20261003T090426Z` | final / filter-labels-consent | 4 | 0 |
| `20261003T091509Z` | release | 14 | 10 |
| `20261003T091942Z` | release / header-public-contacts-.*-(981|1200|1201|1280|1440)$ | 10 | 0 |

| Check | Result | Observed run |
|---|---|---|
| F01-uk-390 | PASS | `20261003T083751Z` |
| game-dialog-uk-390 | PASS | `20261003T083751Z` |
| form-picker-uk-390 | PASS | `20261003T083751Z` |
| F01-uk-1440 | PASS | `20261003T083751Z` |
| game-dialog-uk-1440 | PASS | `20261003T083751Z` |
| form-picker-uk-1440 | PASS | `20261003T083751Z` |
| empty-send-uk | PASS | `20261003T083751Z` |
| duplicate-filters-uk | PASS | `20261003T083751Z` |
| GET-error-retry-uk | PASS | `20261003T083751Z` |
| FAQ-draft-no-PII-uk | PASS | `20261003T083751Z` |
| pricing-mobile-bar-uk | PASS | `20261003T091509Z` |
| partial-tariffs-uk | PASS | `20261003T083751Z` |
| empty-tariffs-uk | PASS | `20261003T083751Z` |
| F01-ru-390 | PASS | `20261003T083751Z` |
| game-dialog-ru-390 | PASS | `20261003T083751Z` |
| form-picker-ru-390 | PASS | `20261003T083751Z` |
| F01-ru-1440 | PASS | `20261003T083751Z` |
| game-dialog-ru-1440 | PASS | `20261003T083751Z` |
| form-picker-ru-1440 | PASS | `20261003T083751Z` |
| empty-send-ru | PASS | `20261003T083751Z` |
| duplicate-filters-ru | PASS | `20261003T083751Z` |
| GET-error-retry-ru | PASS | `20261003T083751Z` |
| FAQ-draft-no-PII-ru | PASS | `20261003T083751Z` |
| pricing-mobile-bar-ru | PASS | `20261003T091509Z` |
| partial-tariffs-ru | PASS | `20261003T083751Z` |
| empty-tariffs-ru | PASS | `20261003T083751Z` |
| matrix-uk-320-no-preference | PASS | `20261003T083751Z` |
| matrix-uk-320-reduce | PASS | `20261003T083751Z` |
| matrix-uk-390-no-preference | PASS | `20261003T083751Z` |
| matrix-uk-390-reduce | PASS | `20261003T083751Z` |
| matrix-uk-768-no-preference | PASS | `20261003T083751Z` |
| matrix-uk-768-reduce | PASS | `20261003T083751Z` |
| matrix-uk-1440-no-preference | PASS | `20261003T083751Z` |
| matrix-uk-1440-reduce | PASS | `20261003T083751Z` |
| matrix-uk-1920-no-preference | PASS | `20261003T083751Z` |
| matrix-uk-1920-reduce | PASS | `20261003T083751Z` |
| matrix-ru-320-no-preference | PASS | `20261003T083751Z` |
| matrix-ru-320-reduce | PASS | `20261003T083751Z` |
| matrix-ru-390-no-preference | PASS | `20261003T083751Z` |
| matrix-ru-390-reduce | PASS | `20261003T083751Z` |
| matrix-ru-768-no-preference | PASS | `20261003T083751Z` |
| matrix-ru-768-reduce | PASS | `20261003T083751Z` |
| matrix-ru-1440-no-preference | PASS | `20261003T083751Z` |
| matrix-ru-1440-reduce | PASS | `20261003T083751Z` |
| matrix-ru-1920-no-preference | PASS | `20261003T083751Z` |
| matrix-ru-1920-reduce | PASS | `20261003T083751Z` |
| delivery-map-uk-320 | PASS | `20261003T091509Z` |
| delivery-map-uk-390 | PASS | `20261003T091509Z` |
| delivery-map-uk-768 | PASS | `20261003T091509Z` |
| delivery-map-uk-1440 | PASS | `20261003T091509Z` |
| delivery-map-uk-1920 | PASS | `20261003T091509Z` |
| delivery-map-ru-320 | PASS | `20261003T091509Z` |
| delivery-map-ru-390 | PASS | `20261003T091509Z` |
| delivery-map-ru-768 | PASS | `20261003T091509Z` |
| delivery-map-ru-1440 | PASS | `20261003T091509Z` |
| delivery-map-ru-1920 | PASS | `20261003T091509Z` |
| Enter-receipt-no-draft-uk | PASS | `20261003T085936Z` |
| F07-main-carousel-cap-uk | PASS | `20261003T085936Z` |
| filter-labels-consent-uk-320 | PASS | `20261003T090426Z` |
| filter-labels-consent-uk-390 | PASS | `20261003T090426Z` |
| Enter-receipt-no-draft-ru | PASS | `20261003T085936Z` |
| F07-main-carousel-cap-ru | PASS | `20261003T085936Z` |
| filter-labels-consent-ru-320 | PASS | `20261003T090426Z` |
| filter-labels-consent-ru-390 | PASS | `20261003T090426Z` |
| header-public-contacts-uk-320 | PASS | `20261003T091509Z` |
| header-public-contacts-uk-981 | PASS | `20261003T091942Z` |
| header-public-contacts-uk-1200 | PASS | `20261003T091942Z` |
| header-public-contacts-uk-1201 | PASS | `20261003T091942Z` |
| header-public-contacts-uk-1280 | PASS | `20261003T091942Z` |
| header-public-contacts-uk-1440 | PASS | `20261003T091942Z` |
| header-public-contacts-ru-320 | PASS | `20261003T091509Z` |
| header-public-contacts-ru-981 | PASS | `20261003T091942Z` |
| header-public-contacts-ru-1200 | PASS | `20261003T091942Z` |
| header-public-contacts-ru-1201 | PASS | `20261003T091942Z` |
| header-public-contacts-ru-1280 | PASS | `20261003T091942Z` |
| header-public-contacts-ru-1440 | PASS | `20261003T091942Z` |

## Recorded hard failures

These remain recorded even if a later build passes.

- `20261003T083751Z` / `pricing-mobile-bar-uk`: Locator expected to be visible. Current case: PASS.

- `20261003T083751Z` / `pricing-mobile-bar-ru`: Locator expected to be visible. Current case: PASS.

- `20261003T085936Z` / `delivery-map-uk-320`: Locator expected to be visible. Current case: PASS.

- `20261003T085936Z` / `delivery-map-uk-390`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `delivery-map-uk-768`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `delivery-map-uk-1440`: Locator expected to be visible. Current case: PASS.

- `20261003T085936Z` / `delivery-map-uk-1920`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `delivery-map-ru-320`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `delivery-map-ru-390`: Locator expected to be visible. Current case: PASS.

- `20261003T085936Z` / `delivery-map-ru-768`: Locator expected to be visible. Current case: PASS.

- `20261003T085936Z` / `delivery-map-ru-1440`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `delivery-map-ru-1920`: Locator expected to have count '6'. Current case: PASS.

- `20261003T085936Z` / `filter-labels-consent-uk-320`: Visible local image was not loaded before viewport screenshot. Current case: PASS.

- `20261003T085936Z` / `filter-labels-consent-uk-390`: Visible local image was not loaded before viewport screenshot. Current case: PASS.

- `20261003T085936Z` / `filter-labels-consent-ru-320`: Visible local image was not loaded before viewport screenshot. Current case: PASS.

- `20261003T085936Z` / `filter-labels-consent-ru-390`: Visible local image was not loaded before viewport screenshot. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-uk-981`: Header target height smaller than44px: {'label': 'Обрати дати ', 'width': 157.40625, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-uk-1200`: Header target height smaller than44px: {'label': 'Обрати дати ', 'width': 157.40625, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-uk-1201`: Header target height smaller than44px: {'label': 'Обрати дати ', 'width': 157.40625, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-uk-1280`: Header target height smaller than44px: {'label': 'Обрати дати ', 'width': 157.40625, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-uk-1440`: Header target height smaller than44px: {'label': 'Обрати дати ', 'width': 157.40625, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-ru-981`: Header target height smaller than44px: {'label': 'Выбрать даты ', 'width': 166.484375, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-ru-1200`: Header target height smaller than44px: {'label': 'Выбрать даты ', 'width': 166.484375, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-ru-1201`: Header target height smaller than44px: {'label': 'Выбрать даты ', 'width': 166.484375, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-ru-1280`: Header target height smaller than44px: {'label': 'Выбрать даты ', 'width': 166.484375, 'height': 43}. Current case: PASS.

- `20261003T091509Z` / `header-public-contacts-ru-1440`: Header target height smaller than44px: {'label': 'Выбрать даты ', 'width': 166.484375, 'height': 43}. Current case: PASS.

Observed cause of the pricing failure: the 7-day term button kept focus after scrolling from the booking form to games. The generic `booking-section:focus-within` CSS rule suppressed the mobile bar although the booking section was outside the viewport. Failure screenshots remain in the original run.

Four initial filter captures failed in the screenshot helper because IntersectionObserver inserted visible game images after its first decode pass. The helper now waits for those images to load. These harness failures remain recorded; label geometry assertions had passed before capture.

The packaged map failures exposed a WordPress module-URL mismatch: the entry script carried `?ver=1.6.0`, while the lazy Leaflet chunk imported the same hashed entry without that query. Chromium executed the entry twice and replaced the app, removing the native dialog. The source harness did not reproduce this packaging defect. Original failures remain recorded.

Raw results include viewport measurements, safe intercepted POST bodies, errors, and actual viewport screenshot paths.

Latest JSON: `work/fixes-1.6/browser-report.json`; immutable run: `work/fixes-1.6/browser-results-20261003T091942Z.json`.
