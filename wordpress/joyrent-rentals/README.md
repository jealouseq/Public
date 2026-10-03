# JOYRENT request options

The request route accepts two optional fields in addition to the existing rental/contact fields:

- `securityMode`: `deposit` (default) or `contract`.
- `requestedGame`: a plain-text game title, up to 120 Unicode characters. Empty text is omitted.

The default deposit uses the shop's PS4/PS5 settings. A contract selection asks the shop to review eligibility for rental without a monetary deposit; it is not an approval. Its order metadata uses `pending_document_verification`, and the deposit remains `pending` until staff review. Passport, taxpayer identification and residence registration are checked through a personally agreed process. The website form has no document or photo upload.

Both choices are visible in the manager-only WooCommerce JOYRENT block and the private shop notification. A title outside the catalog is a request to confirm availability, not a sellable item or guaranteed game. Customer receipts remain `awaiting_confirmation` and disclose only the request reference and rental amount. Neither field adds a fee or changes the rental product price.

Explicit `deposit` and an empty game request produce the same canonical fingerprint as older requests. Changing a non-default choice or game title under the same request ID returns a conflict instead of creating a second order.

Version 1.8 migrates only exact published previous managed FAQ, terms and privacy defaults. Custom page titles, text, excerpts and drafts stay intact, as do orders, game records and private notification settings.

Contract reference: [Funbox rental conditions](https://funbox.com.ua/#rec524506397), reviewed 2026-10-03. JOYRENT retains its own deposit amounts; document verification requires manual agreement.

Local verification (all notification attempts are intercepted):

```sh
docker exec -i joyrent-wp php < tests/php/request-options-regression.php
docker exec -i joyrent-wp php < tests/php/request-options-migration.php
```
