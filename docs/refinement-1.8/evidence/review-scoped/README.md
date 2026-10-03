# Independent review: scoped evidence

[Final review](../review.md) accepts `main-DymdWJeq.js` / `main-CBL0otFI.css` with no unresolved P0/P1/P2 findings. These independent component checks are separate from the main release acceptance and are not added to its case count.

| Scope | Receipt | Build / purpose |
|---|---|---|
| Final map attribution, complete zone extents | [8 cases](landscape-map/receipt.json) | `main-DymdWJeq.js`; UA/RU 320/390/768/980px after credit placement correction |
| Earlier map/fee measurements | [18 cases](attribution-before/receipt.json) | `main-CYezEY1c.js`; before attribution fix; these did not assert credit/zone non-overlap |
| Warm mobile inter-step lines | [8 cases](mobile-connectors/receipt.json) | `main-CYezEY1c.js`; UA/RU 320/390/700/701px; this process rule is retained in final build |
| Earlier compact-process centering | [12 cases](compact-history/receipt.json) | `main-seI-bBsh.js`; prior compact/no-line state, retained only as history |

All available matching reviewer screenshots are copied beside each receipt. They are cropped component captures, distinct from the selected full viewport release screenshots under `../../screens/`. The receipts preserve their original measured build and dimensions. [Exported paths, SHA256 and byte equality](export-receipt.json).

Useful comparisons: [credit before at RU320](attribution-before/ru-320-map-fees.png) / [final RU320](landscape-map/ru-320-map-fees.png); [final UA768](landscape-map/uk-768-map-fees.png), [final RU980](landscape-map/ru-980-map-fees.png); [warm lines RU320](mobile-connectors/ru-320-process.png) and [UA700](mobile-connectors/uk-700-process.png).

The final report also verifies six projected paths/all 219 geographic vertices, real OpenStreetMap roads/coastline/database provenance, and byte equality of relevant theme/preview runtime assets. All browser non-GET requests were blocked before transmission; no form contacts were entered and no order/email was sent. Only safe receipt JSON and PNG files are exported here; executable checks remain in ignored `work/`.
