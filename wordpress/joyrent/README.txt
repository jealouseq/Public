JOYRENT WordPress theme 1.9.19
Requires WordPress 6.6+, PHP8.2+, JOYRENT Rentals and WooCommerce.

Install the ZIP through Appearance > Themes > Add New > Upload Theme and replace the existing JOYRENT theme. The folder remains joyrent; owner pages, content and plugin settings are not migrated by this theme update.

Reliability changes:
- Malformed successful API responses cannot show a false accepted booking or clear its saved selection. Retrying an uncertain submission retains its request identity.
- Name, address and optional game wish are validated as plain text before submission. Telegram profile URLs accept case-insensitive HTTPS/t.me consistent with the rental API.
- Published legal and FAQ links work with both plain and pretty WordPress permalinks. Missing/draft pages use safe fallbacks.
- Initial and switched UA/RU page metadata use the same server-generated title, description and canonical. Open Graph dimensions match the actual image.
- Existing global and bot-scoped crawler restrictions are preserved.
- Native 404, empty search and archives have useful text, linked titles and pagination.
- Terms/privacy and delivery-map dialogs keep keyboard Tab navigation inside the dialog and restore opener focus.
- The booking price summary remains part of its form instead of introducing a nested complementary landmark. Player-count badges and delivery-zone legend have meaningful accessible roles.
- Development test/image tools updated to patched releases; they are not included in the WordPress ZIP.

Verification uses disposable WordPress/WooCommerce with blocked mail/Telegram and both mobile/desktop layouts. Browser tests cover Chromium behavior; a complete real-device Safari/VoiceOver audit or production hosting performance is not implied. Approved artwork, fonts, colors, mobile composition and the separate FAQ page remain unchanged.
