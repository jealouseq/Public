# JOYRENT request options

The request route accepts two optional fields in addition to the existing rental/contact fields:

- `securityMode`: `deposit` (default) or `contract`.
- `requestedGame`: a plain-text game title, up to 120 Unicode characters. Empty text is omitted.

The default deposit uses the shop's PS4/PS5 settings. A contract selection asks the shop to review eligibility for rental without a monetary deposit; it is not an approval. Its order metadata uses `pending_document_verification`, and the deposit remains `pending` until staff review. Passport, taxpayer identification and residence registration are checked through a personally agreed process. The website form has no document or photo upload.

Both choices are visible in the manager-only WooCommerce JOYRENT block and the private shop notification. A title outside the catalog is a request to confirm availability, not a sellable item or guaranteed game. Customer receipts remain `awaiting_confirmation` and disclose only the request reference and rental amount. Neither field adds a fee or changes the rental product price.

Explicit `deposit` and an empty game request produce the same canonical fingerprint as older requests. Changing a non-default choice or game title under the same request ID returns a conflict instead of creating a second order.

Version 1.8 migrates only exact published previous managed FAQ, terms and privacy defaults. Custom page titles, text, excerpts and drafts stay intact, as do orders, game records and private notification settings.

Managed FAQ and terms also follow business-setting changes: exact bundled pages switch to neutral wording when conditions differ from the approved defaults. Owner-authored pages stay intact. The private notification recipient and the search-indexing setting are never returned by the public catalog. Search indexing is disabled by default until a manager enables it after moving to the main domain.

Published games are wishes awaiting manual confirmation. The `available` flag records shop verification of availability and license; `false` means unverified and does not block a wish. To hide a game from the public catalog, change its publication status to draft. Rental tariffs respect WooCommerce stock settings, while availability for specific dates is still confirmed manually.

Completed requests replay before current date, game-publication, pickup or stock eligibility is checked. Canonical intent ignores phone formatting and game-selection order; older cached fingerprints remain compatible through the durable order. Pickup addresses are bounded and then discarded. Receipts continue to acknowledge manual confirmation and contain no contact or document data.

Contract reference: [Funbox rental conditions](https://funbox.com.ua/#rec524506397), reviewed 2026-10-03. JOYRENT retains its own deposit amounts; document verification requires manual agreement.

Local verification (all notification attempts are intercepted):

```sh
docker exec -i joyrent-wp php < tests/php/request-options-regression.php
docker exec -i joyrent-wp php < tests/php/request-options-migration.php
```

The isolated robustness suites run without WordPress, a database, orders or email:

```sh
php tests/php/request-robustness-isolated.php
php tests/php/settings-stock-isolated.php
php tests/php/current-settings-migration-isolated.php
```

## 1.8.6 reliability update

- A receipt is returned only after the WooCommerce order, its durable key, rental item and completed booking status are verified in storage. WooCommerce can swallow a save exception; this no longer produces a false JR-0 / accepted receipt or orphan items.
- Totals are calculated while the order is still incomplete, before booking notifications. The retained `jr-incomplete` status keeps interrupted bookings and their durable keys visible for manager review; WooCommerce draft cleanup cannot remove them. A partially saved completed flag without a final accepted status never acknowledges a receipt or triggers email.
- Concurrent retries receive a busy response while the first worker holds the request mutex; an abandoned unfinished booking remains blocked for review.
- Email is sent only after its sending-attempt marker is verified in storage. An uncertain attempt is not silently resent. Email uses wp_mail; actual delivery still depends on the host or configured mail provider.
- Public game records are read in batches, stop at 100 valid unique games, and tolerate malformed metadata. Stale WooCommerce SKUs and nonfinite prices do not crash the catalog.
- Required contact text rejects embedded controls, markup and invalid UTF-8. Public settings normalize types and unknown amounts to pending without rewriting stored owner values.
- Valid previous request keys, CPT / HPOS, owner prices, pages, settings and Telegram notifications remain compatible. Updating an existing 1.8.5 install does not repeat the old copy/contact migration.

Replace only JOYRENT Rentals with joyrent-rentals-1.8.6.zip through WordPress's plugin upload. WooCommerce and JOYRENT Telegram remain installed.
