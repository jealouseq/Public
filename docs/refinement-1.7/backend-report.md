# JOYRENT 1.7 — backend copy and managed-page migration

JOYRENT 1.7 backend copy fixes are complete. No commit, live write, live request or external email was made. Plugin header and migration target are 1.7.0; theme/package versioning and native noscript remain root-owned. Theme functions/templates were not edited.

Changed files:

- `wordpress/joyrent-rentals/data/faq.json`: UA/RU second controller is free; deposits are PS4=7 500 and PS5=25 000; internet may also be required for single-player games. Unknown payment method and deposit-return timing remain subject to agreement.
- New `data/legal-pages.json`: current four default legal/privacy pages. Terms reflect approved controllers/deposits/Odessa delivery zones and retain manual confirmation. Privacy asks users not to paste sensitive data and points to the approved public Telegram/phone channels.
- New `data/page-seeds-1.6.json`: exact earlier FAQ/legal seed snapshots used solely to recognize unchanged managed pages.
- `includes/store.php`: exact-copy migration and safe page creation. Published pages update only when title/content equal the previous seed and excerpt is empty. Author content/title/excerpt and drafts are preserved. 1.6→1.7 does not reseed games or rewrite business settings/orders; older upgrades retain their existing 1.6 steps. New product seed copy distinguishes deposit payment/return arrangements from the approved deposit amount.
- `includes/domain.php` / `locale.php`: informal UA visitor validation and natural UA/RU game-availability wording. Validation rules and request fingerprints are unchanged.
- `joyrent-rentals.php`: plugin version 1.7.0.
- New `tests/php/copy-migration.php`: local migration and copy regressions.

The business-copy guard applies to FAQ/terms both when updating and creating a page. The approved claims require matching UA/RU Odessa city, PS4/PS5 deposits, two included controllers with zero extra fee, unknown short delivery fee, green/yellow 200/300 fees and threshold 7. If owner conditions differ, existing neutral FAQ/terms remain unchanged and a missing managed FAQ/terms page uses the archived neutral seed. Privacy can migrate independently. Custom pages, business/private settings, game IDs and order IDs remain intact.

Verification:

- Initial migration regression reproduced seven failures before the update: all six previously seeded pages stayed stale and the version did not advance (`copy-red.json`).
- Reviewer custom-condition regression reproduced eight failures before the guard (`copy-business-red.json`). The independent short-fee 125 case reproduced two failures before adding `deliveryFee=null` to the guard (`copy-short-fee-red.json`).
- Final 42 assertions pass ([safe receipt](evidence/copy-business-green.json)): all six exact seeded pages update in place, custom pages remain intact, custom deposit/controller/zone/threshold/city/short-fee settings avoid contradictory claims, missing managed pages use neutral copy, privacy updates independently, visitor language matches, and settings/private keys/games/orders remain unchanged. Mail attempts=0. Fixtures restore their originals.
- Existing pure PHP domain test: 15 assertions pass. All nine plugin PHP files pass syntax checking (`php-lint.log`); changed store.php was checked again after the final guard.
- Known intermediate development defaults were aligned to final copy without replacing custom page content (`local-native-copy.json`). All six native local routes returned 200 with the expected final FAQ/legal/privacy copy (`native-page-get.json`). No external mail attempts.

Repeat the targeted fixture between browser QA passes:

```sh
docker cp tests/php/copy-migration.php joyrent-wp:/tmp/copy-migration.php
docker exec joyrent-wp php /tmp/copy-migration.php
```

Game descriptions/owner metadata, tariff prices, private notification destination and operational request/delivery rules were not changed in this copy pass. BO7 duplicate-note suppression is owned by the frontend worker. Native noscript localization is owned by root.
