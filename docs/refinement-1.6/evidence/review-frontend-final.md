# JOYRENT 1.6 scoped frontend re-review

Scope: the three findings from `review-frontend.md` only. Reviewed current source, narrow regression scripts/results and fresh filter screenshots. No product edits, repeated full suite, orders, email or live mutations.

## Verdict

**Spec: prior findings resolved. Quality: no remaining Important or Critical finding in this reviewed scope.** Final packaged/integration acceptance, Enter/success/pagehide cases and Leaflet review remain separate checks; this report does not claim they passed.

| Prior finding | Resolution and evidence |
|---|---|
| I01: misleading Add beyond capacity | App passes the shared `maxGames` to Games. `src/sections/Games.tsx:28` disables only an unselected Add at capacity and provides a local UA/RU explanation. `GameDetailDialog` renders that explanation and uses its disabled button, so no failed add closes as if successful. Selected Remove remains enabled when catalog is ready. `frontend-capacity-green.json` and its narrow script verify Add refusal, local notice, removal and zero POSTs in UA/RU. |
| I02: overlapping mobile category labels | `src/styles.css:808` applies `flex: 0 0 auto` to the shared filter button, preserving its text width and minimum 44px target while the row scrolls. `media-filters-after.json` checks main and picker rows in UA/RU at 320/390: zero overlaps, every label inside its button, minimum target height 44px, no page/picker overflow. Fresh UA picker and RU main filter crops visually confirm readable spacing. |
| M01: small consent text | `src/booking.css:8–10` explicitly uses 13px consent text with 1.65 line height and 44px legal actions. `frontend-consent-narrow.json` records UA/RU 320/390: 13px text, 44px action heights and zero overflow. Inline targets remain large enough without reverting the readability improvement. |

These corrections preserve the existing visual style and require no new dependencies or promotional text. Historical failing evidence remains valid for the earlier source and should not be rewritten. The native 1448px desktop hero DPR3 limitation from the media report is unchanged and must remain disclosed.
