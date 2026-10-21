# Address suggestion touch click-through — independent packaged reproduction

**Confirmed P2:** tapping the second street suggestion can select the street and open an unrelated privacy dialog underneath the suggestion popup.

Production-source locations: `src/components/OdesaAddressInput.tsx:32` (window capture pointerup handler), `:39–40` (preventDefault + immediate commit), `:131` (popup close), `:218–220` (pointer-driven option and separate accessibility activation). Underlying control: `src/components/BookingConsent.tsx:20`; legal opening: `src/App.tsx:62`.

Read `superpowers:systematic-debugging` before investigation. Tested current packaged theme on `http://localhost:8080/`, Chromium, 390×844, DPR 2, `has_touch:true`, reduced motion. Also confirmed with `is_mobile:true`. Every non-GET request was blocked before transmission; external GETs were blocked. No source/build/archive edits or real submission.

## Minimal reproduction

1. Fresh page/context; Continue to contact fields.
2. Fill name `QA Local`, phone `+380500000000`, and address prefix `Фонта`.
3. Tap the second option (`Середньофонтанська вулиця`) at its center, approximately CSS `(195, 464.78)`.
4. Address changes correctly, then the privacy dialog opens without a separate consent-link tap.

At this scroll position, the second option occupies x30/y440.78/w330/h48. The consent privacy button below it occupies x76/y461.67/w193.11/h22.09. Thus the option's center lies over the privacy button once the popup disappears.

## Event evidence and root cause

The independently captured production event sequence is:

- `pointerdown` (`pointerType:'touch'`) targets the second option's SPAN while the popup exists.
- The component's global capture `pointerup` runs first, calls `preventDefault()`, then invokes `press.commit()`.
- `select()` immediately changes the address and calls `setOpen(false)`. React removes the popup before click dispatch.
- The later capture logger sees that same `pointerup` still targeted the old SPAN, with `defaultPrevented:true`; `elementFromPoint(195,464.78)` already returns the consent privacy BUTTON and the popup is absent.
- A few milliseconds later, Chromium emits `click` (`pointerType:'touch'`, `detail:1`) targeting the exposed privacy BUTTON, with `defaultPrevented:false`. That click opens the legal dialog.

`preventDefault()` on pointerup does not consume this subsequent touch click. This is a target-lifetime/gesture-completion issue, not a street-matching problem or a deliberate privacy-button activation.

Working comparison: a mouse click at the identical option center selects the street with no dialog. Keyboard selection had separately passed the initial audit. Both ordinary and `is_mobile:true` touch contexts reproduce the unwanted dialog.

## Minimal diagnostic hypothesis test

A browser-only instrumentation experiment added a one-gesture capture guard after a prevented touch pointerup. It consumed only the matching touch click at the same coordinates before it reached React. The street still selected, the guard recorded the exposed privacy-button click, and no legal dialog opened. This counterfactual confirms the click-through root cause. It is diagnostic page instrumentation, not an applied production fix.

Focused recommendation: preserve the original option/overlay hit target until the gesture's click completes, or explicitly consume the matching compatibility click for a successfully committed touch gesture. Do not solve this by disabling the underlying consent controls or moving them. Any eventual fix should preserve drag/cancel behavior, mouse selection, keyboard Enter, and VoiceOver/programmatic `detail:0` activation. A browser regression should assert **street selected and zero new dialogs/underlying activations** after the touch tap; asserting only the address value misses this defect.

## Artifacts

- `touch-address.py`: reproducible safe packaged investigation, all writes blocked.
- `touch-address.json`: events, geometry, target changes, guard experiment, errors and blocked-write counts for touch, mouse, diagnostic guard and mobile touch.
- `touch-address-touch-before.png` / `touch-address-touch-after.png`.
- `touch-address-touch-mobile-before.png` / `touch-address-touch-mobile-after.png`.
- `touch-address-touch-guard-diagnostic-after.png`: street selected with no unrelated dialog.

All cases report zero writes attempted and zero JavaScript errors. Git remains clean. Independent live agent reproduced the same event sequence, including a second scroll position, so this is not confined to the local packaged fixture.
