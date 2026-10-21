Mobile hero refinement — visual QA and acceptance evidence

Source: the latest user-selected 1280×1132 chat attachment, opened and reviewed by root. Its local source file/file ID is unavailable to this subagent. Root confirmed that the 390px and 320px rendered captures match the selected composition intent. This is an approximate responsive adaptation, not a claim of pixel-perfect reproduction. The reference crop ends after the price; existing console imagery continues below in the implemented page.

Implementation: local Vite dev source at localhost:5173, UA/RU320/390/700/701/1440, native Chromium screenshots at deviceScaleFactor1. Main comparison capture: `after-uk-390.png`, with `content-uk-390.png` for the heading/action/price region. Secondary comparison: `after-uk-320.png` and Russian equivalents. Root's visual comparison uses the original chat attachment and these captures. No raster reference was recreated or new asset generated.

Required surfaces checked:
- Typography: existing Unbounded heading550 and Manrope body retained. The heading has exactly two lines in both languages; the final font formula is26px–50px with8.3vw. The two description sentences stay separate, centered and readable at14px; narrow320px permits an extra body-text line. UI labels remain stable during hover.
- Layout: centered eyebrow→two-line heading→description→primary action→tariff link→price, photo below. Final primary action is248×54px with15px text and a34px inset caret. The label and caret use separate grid columns with balanced padding. Existing header and desktop701px/1440px computed element states are unchanged from fresh baseline captures.
- Colors: original near-black background, ivory foreground, muted gray supporting text and warm focus indicator retained. CTA ivory pill has only a small inset caret container; no large glow or perpetual shimmer.
- Image: existing responsive PS5 picture sources, masks, photo scale and scene animation retained. No new generation, stretching or raster substitution.
- Copy: current approved UA/RU sentences and CTA behavior retained. A native accessible anchor routes to#rates and invokes the existing console selection handler. Title accessible label retained; animated duplicate visual text remains aria-hidden.

Iteration history:
1. Initial390/320 visual capture accepted by root for composition. Browser measurements found aP2: a few pixels of second-line letter boxes could extend outside the content column with8.55vw despite no viewport scrollbar.
2. Changed only the mobile formula to8.3vw. Recaptured all10 viewports and checked letter-box bounds plus desktop/header preservation. See `browser-check.json` for final measurements. Reduced-motion's global0.01ms rule is treated as effectively disabled; explicit hero motion/transforms are removed in the reduced-motion media rule.
3. Following the user's further sizing request, increased the first204×52px CTA to248×54px and its text to15px. Final packaged UA/RU320/390 captures confirm this size and no horizontal overflow. The earlier source-only receipt records the preceding size; [final packaged receipt](../browser-check.json) and [native before/after board](../hero-before-after.png) document the released result.

Interactive Hover Button, Shiny Button and Shimmer Button public21st.dev metadata, previews and compiled bundles were inspected. All declare Dillion Verma/MagicUI/MIT. Used only the compact pill/inset-icon interaction principle from Interactive Hover Button; source TSX was not copied, registry API key/install was not used. Public reference evidence: [21st.dev references](../references/21st-buttons.json).

Verification scope: initial source-only acceptance followed by final packaged WordPress UA/RU320/390/1440 verification and two short-viewport scenarios. All final browser screenshots were inspected. Six armed local submission fixtures were intercepted before transmission; no real order or email was sent. Physical Safari keyboard and WebKit behavior are outside this Chromium visual check.

final result: passed
