# JOYRENT 1.6 integration follow-up review

Scope: the new zone-dependent delivery persistence in `orders.php`, the queryless hashed asset enqueue, and the homepage-only WooCommerce script dequeue in `functions.php`. Reviewed current code/diff, the targeted delivery regression, and its red/green evidence. No production edits, state-mutating fixture tests, requests POSTs, or mail attempts were performed during this review.

**Verdict: approved for the reviewed scope. No Critical, Important or Minor findings.**

## Delivery eligibility

`wordpress/joyrent-rentals/includes/orders.php:34`–46 now treats a long-rental address as an unconfirmed zone. New delivery requests meeting the configured free threshold persist `_joyrent_delivery=pending` and `_joyrent_delivery_free_eligibility=pending_zone_confirmation`, with an order note limiting free delivery to confirmed green/yellow addresses and retaining return-inclusive taxi pricing for red. Pickup remains zero; configured short delivery fees remain chargeable. Neither domain validation nor creation trusts a client `deliveryZone` claim.

The change affects only a newly created `WC_Order`. No migration/rewrite of existing orders was added; durable-key recovery and cached request replay remain unchanged. Rental amount/receipt semantics remain rental estimates. The updated UI delivery calculation also stays pending for long requests and labels the included benefit specifically for green/yellow zones with address confirmation, matching this boundary.

Inspected `tests/php/delivery-zone-regression.php` and artifacts: red has 33 checks with nine expected failures; green has 33 checks with zero failures and nine intercepted mail attempts. Cases cover 3/7/30 days, null/configured fees, ignored client green claims, eligibility/qualified note, pickup, rental totals, and unchanged historic metadata. The test restores the complete original settings option, including the private notification setting. These mutating tests were not rerun by this reviewer.

## Asset enqueue and WooCommerce isolation

`wordpress/joyrent/functions.php:40`–44 supplies `null` versions for hashed CSS/main entry filenames. The built lazy Leaflet chunk imports `./main-pObJiJ8Q.js`, and the live local page now references that same queryless main filename. This removes the distinct `main.js?ver=...` module identity that could execute the bootstrap twice. Hashed filenames continue to provide cache busting; inline boot configuration still precedes the module entry.

`wordpress/joyrent/functions.php:86`–89 dequeues the enumerated WooCommerce consumer scripts only when WooCommerce exists, the route is the front page, and it is not cart/checkout/account. The short circuit also preserves operation without WooCommerce. No global deregistration or dependency edits were added.

Independent read-only GET checks:

| Route | Result |
|---|---|
| `/` and `/?lang=ru` | HTTP 200, exactly one external script: the queryless hashed JOYRENT main; queryless hashed CSS; no WooCommerce scripts. |
| `/cart/` | HTTP 200, WooCommerce cart/core/cookie/attribution/block scripts retained; no JOYRENT main. |
| `/my-account/` | HTTP 200, WooCommerce account/core/cookie/attribution scripts retained; no JOYRENT main. |
| `/checkout/` with empty cart | Redirects to cart, which retains WooCommerce scripts. Direct nonempty checkout behavior was not exercised in this read-only pass; the explicit checkout exclusion is present. |

Independent PHP lint for both changed production files passed. An independent read-only catalog check confirmed the private notification key and value remain excluded from public data, without printing a receiver. The scoped production changes do not write notification settings or alter notification delivery/retry handling; actual mailbox delivery remains unverified.
