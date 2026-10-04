# UA/RU visitor copy review — 2026-10-04

Read-only follow-up at HEAD `b9b16e8`, theme 1.9.2/plugin 1.8.1. Reviewed all current `src/`/`components/` visitor strings, native PHP header/footer/FAQ/noscript strings, current FAQ/legal/neutral seeds, server validation locale strings, and all 20 live game descriptions. There is no `src/content/` directory in this source tree.

Live evidence: `/workspace/work/audit-2026-10-04/assets/live-public-get.json` (captured 12:07 UTC) and `live-plugin-default-comparison.json`. This pass made no new live requests or writes. Current live JS/CSS matching is established by the asset audit.

## Confirmed copy mismatch

**C1 — P3, one-day PS4 implication.** `src/sections/Hero.tsx:20` says `PS5 або PS4 на день, вихідні чи довше.` / `PS5 или PS4 на день, выходные или дольше.` Both imply that the visitor can choose either console for one day. Current live and seed tariffs offer PS4 only for 3, 7 and 30 days (`wordpress/joyrent-rentals/data/catalog.json:37`; live catalog resource `/wp-json/joyrent/v1/catalog`). There is no one-day PS4 option.

Possible concise replacement within the existing sentence, without adding a paragraph: `PS5 — від 1 дня, PS4 — від 3 днів.` / `PS5 — от 1 дня, PS4 — от 3 дней.` This is a factual alignment proposal, not a request to expand hero marketing copy.

## Optional wording and clarity proposals

- **Process step covers only deposit.** `src/sections/Story.tsx:41` says `Узгодимо доставку та заставу.` / `Согласуем доставку и залог.` The form also supports a contract without deposit after manual document checking. Existing sentence could say `Узгодимо доставку й умови оформлення.` / `Согласуем доставку и условия оформления.` This is a small consistency improvement; surrounding form/FAQ/legal text already makes the two choices clear.
- **Hero daily-price denominator.** `src/sections/Hero.tsx:22` shows `від 600 грн/день` / `от 600 грн/день` for the first PS5 one-day tariff. Current PS5 30-day tariff costs 6,000 грн, equivalent to 200 грн/day. `src/App.tsx:66` passes the first duration/price, not the minimum daily equivalent. This is an optional clarity observation: the current amount is correct for the one-day tariff, but the word `від`/`от` does not explain that basis. If the existing hero price wording was explicitly approved, retain it; a factual `1 день — 600 грн` label would clarify that same amount without advertising a different tariff.
- **Unlisted-game action terminology.** The empty-search CTA at `src/components/GamePicker.tsx:77` says `Запросити гру` / `Запросить игру`, while the rest of the feature uses `побажання` / `пожелание`. Optional `Додати побажання` / `Добавить пожелание` would match the saved state, but this is not an old booking-request term: it means an unlisted-game wish. The search message `За цим запитом...` / `По этому запросу...` refers to the search query and should not be mechanically replaced with booking words.
- **Conditional neutral legal register.** `wordpress/joyrent-rentals/data/page-defaults-neutral.json:12` and `:16` begin with `тобой`/`тобою`, then switch to `Не разбирайте...` / `Не розбирайте...`. These neutral terms can be selected when settings differ from approved defaults (`store.php:177`). They are not the current live legal text. Keeping singular `Не разбирай...` / `Не розбирай...` would align the voice when this branch is used. The same neutral branch only mentions deposit; a generic `умови оформлення`/`условия оформления` phrase would cover the contract option without inventing custom-shop terms.

## Consistency verified

- No current visitor-facing `заявка`, `заявку`, `заявці`, or equivalent Russian booking-request wording remains in the React interface, current FAQ/legal/neutral seeds, public native UI, or saved live FAQ/legal paragraphs. Current server-facing booking errors use `бронювання` / `бронь` / `бронирование`.
- Historical `page-seeds-1.6.json`, `-1.7.json`, and `-1.8.json` intentionally contain earlier wording used for exact migration matching (`store.php:171–174,190`). Staff-only order notes/status internals also contain technical request terminology. Neither is evidence of a current visitor-copy regression; do not rename machine IDs or rewrite migration snapshots.
- Live FAQ matches both current language seeds. All four live legal pages match current legal seed text. Deposit values are consistently PS4 7,500 грн / PS5 25,000 грн. The contract path requires personal document checking; no document-upload/payment claim is present.
- Current live delivery settings are Odesa, green 200 грн/yellow 300 грн round trip, free green/yellow from 7 days, red by taxi in both directions, and outside zones by agreement. These rules are explicit in current legal text and in the landing delivery section/map. Booking summary qualifies the included green/yellow zones. The short approved tariff badge `Доставка включена` is preserved and not classified as a defect.
- The second controller is consistently free in live settings, kit, form, FAQ and terms. The form allows 1 or 2 controllers, while game player labels describe supported local players rather than controllers supplied.
- Player inflection is correct for offered counts. Console-specific local counts are used by both filters and game details: Call of Duty PS5 2/PS4 1; Gran Turismo PS5 4/PS4 2. Detail copy says `До...` to indicate capacity.
- Live Call of Duty description is less explicit about internet than the updated local seed, but live `requiresInternet:true` causes `GamePresentation.tsx:47` to add the explicit UA/RU internet note. This difference is not a missing visitor warning.
- Validation strings and primary footer/contact CTAs keep the same singular, direct UA/RU voice. Hidden honeypot `Ваш сайт` is technical bot-trap copy, not normal visitor text. Success explicitly states pending personal confirmation and no payment required.

Native page browser titles use the WordPress site-name suffix `flowers-luxury.shop` and descriptions reuse landing metadata. The asset agent already reported these confirmed identity/metadata issues; avoid double counting them as new copy findings.

No text changed. No broad rewrite, extra marketing paragraph, new approval flow, or delivery-badge reversal is proposed.
