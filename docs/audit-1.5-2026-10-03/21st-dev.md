# 21st.dev reference audit for JOYRENT 1.5

Evidence gathered 2026-10-03. Recommendation: use 21st as a source for restrained interaction patterns and booking clarity while keeping JOYRENT's own PS5 studio art, Unbounded/Manrope typography and dark visual identity. The strongest gains are legible product selection, an explicit rental date range, and a short concrete explanation of the rental process. Adding more visual effects will contribute less than these.

## Scope and evidence

- Mapped the public site's major surfaces: component catalog, 144-library index, authors, templates (Included / Free / Paid / Verified), and themes.
- Extracted the current catalog's 75 linked category labels. ASCII Art, Gradients and Shaders also appear as special navigation labels.
- Fetched 30 category markdown inventories, containing 1,759 listed links / 1,458 distinct component or variant URLs. These are inventory entries, not 1,458 individually inspected components.
- Read concrete author-page metadata for the shortlist and supporting alternatives. Viewed nine publisher component preview images and three publisher template preview images.
- Did not interact with live demos or verify keyboard behavior, responsive states, animation timing or implementation correctness. Static previews prove composition only.
- Component HTML identifies TSX sources as private registry objects. Public page metadata and public demo bundle URLs were accessible; original component TSX was not inspected or installed.
- The homepage extraction and current category HTML disagree about category counts; category markdown also uses capped totals and old descriptor counts. Therefore navigation labels and link coverage are recorded separately. These numbers are not presented as a verified size of the entire catalog.
- Several legacy aliases returned 404 as markdown: navbar-navigation and modal-dialog. Current navigation-menu and dialog indexes work.

## Five references worth carrying into the 1.5 discussion

| Exact author URL | What was verified | Principle suited to JOYRENT | Recommendation and actual published costs |
| --- | --- | --- | --- |
| https://21st.dev/@yadwinder/components/vercel-tabs | Author metadata and static image: ordinary horizontal labels, a single active underline, subdued inactive labels | Quiet game-category navigation; preserve the existing clear PS5 / PS4 pill; selected state should be obvious before animation | Use the plain underline/label pattern where product selection needs improvement. Declares MIT. Metadata lists no npm dependencies; that is not a proof of zero transitive/registry dependencies. CSS can express the visual principle without an import. |
| https://21st.dev/@shadcnspace/components/calendar-range-select | Metadata explicitly describes booking-compatible range selection; static image shows distinct endpoints and a continuous range | Make start, finish and intervening rental days easy to read | Adapt range styling to the dark palette, retain a textual date summary and the actual rental-day calculation. Declares MIT and react-day-picker. Do not add a calendar library merely for appearance if the existing date model is adequate. Unavailable-date behavior was not verified. |
| https://21st.dev/@olewandowski1/components/how-it-works-2 | Metadata and static image: a vertical sequence, thin connecting line, title plus short supporting copy per step | Explain choose dates → confirm → receive → return without dashboard-like cards | Prefer a short native HTML/CSS process section with meaningful rental copy. Declares MIT, @remixicon/react. The image uses icons rather than explicit numbers; do not infer behavior from the metadata alone. |
| https://21st.dev/@preetsuthar17/components/blurred-stagger-text | Actual author metadata: staggered text with a blur filter | One controlled headline entrance, already adapted by JOYRENT | Retain the existing adaptation if it remains fast and legible; avoid repeating it across body copy or pricing. Declares MIT and motion. JOYRENT already adapts this headline pattern with native CSS; no new dependency is justified. Framer Motion 12 is used elsewhere in the app. Motion behavior was not visually tested here. |
| https://21st.dev/@kokonutd/components/hero-fashion | Metadata and static image: a very large left title with one strong right portrait, generous spacing | Large product photography and a clear editorial hierarchy can create premium character without neon or shaders | Use composition as a reference while keeping JOYRENT's original console art. The fashion navigation, long paragraph and white palette do not match rental tasks. Declares MIT and motion. No template replacement recommended. |

Alternative typography directions were read: [Soft Blur In](https://21st.dev/@educalvolpz/components/soft-blur-in) (MIT, motion; per-character soft blur and upward reveal) and [Slide Up Text](https://21st.dev/@tom_ui/components/slide-up-text) (MIT, motion; word/character/line stagger). They offer no compelling reason to replace the already adapted Blurred Stagger. [Slide Tabs](https://21st.dev/@minhxthanh/components/slide-tabs) declares framer-motion but an unknown license, making the simpler MIT Vercel Tabs reference preferable.

## Breadth beyond text animations

| Category families reviewed | Fit for JOYRENT 1.5 |
| --- | --- |
| Text, heroes, images, galleries | Preserve a strong hierarchy and original console imagery. Keep important copy visible quickly. Gallery grids are useful only when photos add product information. |
| Buttons, tabs, navbar/navigation-menu, menus | Straight labels, solid primary CTA, predictable mobile menu, clear active state. Reviewed a monochrome ReUI button variant and a conventional navbar preview. Their package imports are unnecessary to reproduce these simple visual patterns. |
| Cards, carousels, features, pricing, comparisons | Product facts, included accessories and total cost deserve clear grouping. Repeated nested cards and SaaS plan grids are poor matches for a short rental flow. A thumbnail carousel can be justified by actual console/accessory photos; rotating offers, 3D wheels and autoplay distract from dates and price. |
| Calendar, date-picker, input, form, dialog, steps, timeline | Highest functional relevance: clear interval, unavailable days if supported, labeled fields, visible error placement, concise process. Two-month range in a dialog is a secondary option only if the booking layout needs it. |
| FAQ, footer, CTA, testimonials | Rental rules, deposit, delivery/collection, late returns and contact belong here. Testimonials need real evidence. One clear conversion path has more value than a repeated decorative CTA block. |
| Background, light, border, scroll-area, shader | A subtle static image light or dark surface transition can support the console image. Thin borders and overflow masks can clarify grouping. Avoid animated beams, neon grids, shader canvases, cursor trails, shine borders and scroll-locked hero effects. |
| Hooks and broader utility categories | Internal conveniences may be useful later. The audit gives no basis for adding hooks or packages to improve the visible product. AI chats, dashboards, file trees, globes, charts, profile panels and count-up KPIs do not serve this rental journey. |

Specific supporting pages were read, with costs recorded: [Scroll Fade Effect](https://21st.dev/@moumensoliman/components/scroll-fade-effect) (MIT; no npm list in metadata) supplies only a subtle overflow-edge principle; [Navbar with Theme Toggle](https://21st.dev/@shadcnui-blocks/components/navbar-02) (MIT, lucide-react) is a conventional responsive menu reference, but sign-in/sign-up/theme controls add tasks JOYRENT does not need; [ReUI Card](https://21st.dev/@sean0205/components/card) (MIT, class-variance-authority) is simply content grouping. [ReUI Button / mono](https://21st.dev/@sean0205/components/button-1/mono) was inspected along with its 15 listed demo variant URLs; only the mono static preview was viewed. It declares radix-ui, lucide-react and class-variance-authority.

## Whole-site template and theme samples

The templates index includes portfolios, studio/service websites, storefronts, AI/SaaS pages and dashboard starters. Three representative full-template publisher previews were viewed:

- [Forma pilates studio](https://21st.dev/@larsen66/templates/forma-pilates-studio): calm large image, short headline, visible concrete offer and booking CTA. This is the most useful full-page principle for a rental business: photography plus a clear offer. Its counter/rating row and external Mindbody features are not needed.
- [Aurea dental studio](https://21st.dev/@larsen66/templates/dental-studio): quiet single product still life, brief two-line service statement, primary appointment action and secondary link. Useful as a study in spacing and low visual noise.
- [Elena Voss Portfolio](https://21st.dev/@hyperiux/templates/elena-voss-portfolio-template): large photo with overlapping text, saturated orange/red backdrop and widely separated labels. This is an expressive portfolio direction; its color intensity and overlap are unsuitable for rental booking.

The themes index includes [Graphite Mono](https://21st.dev/@serafimcloud/themes/graphite-mono), [Modern Minimal](https://21st.dev/@serafimcloud/themes/modern-minimal), Vercel, Elegant Luxury and many more. The first two detail pages were fetched, but token values and rendered states were not extracted or visually verified. Theme names are not enough evidence to recommend replacing JOYRENT's palette or fonts. Template licensing and exact download pricing were not verified here.

## Public bundles for root visual checks

- Vercel Tabs: https://cdn.21st.dev/bundled/1597.html
- Blurred Stagger Text: https://cdn.21st.dev/bundled/1858.html
- Hero Fashion: https://cdn.21st.dev/bundled/1847.html
- Range Select Calendar: https://cdn.21st.dev/shadcnspace/calendar-range-select/default/bundle.1787251779179-c0113a96-ba4d-4dec-8f99-65338afcf77b.html
- How It Works Timeline: https://cdn.21st.dev/7ovr/how-it-works-2/default/bundle.1784360674057-83bcf60a-2129-4128-bf8a-9a08c22ba072.html
- Basic Stepper: https://cdn.21st.dev/sean0205/c-stepper-1/default/bundle.1790140579944-e8d24f3b-9e74-4eb7-b83a-9271009b1b68.html

The fetched markdown pages state that registry installation requires a 21st API key and that free accounts have a limited number of installs per day. No key, install or paid action is needed for this design audit; no price amount is claimed.

## Files

taxonomy-evidence.json preserves all 75 navigation URLs and labels. category-evidence.json, marketing-category-evidence.json and booking.evidence.json preserve the 30 category inventories. component-evidence.json and supporting-component-evidence.json preserve author metadata and public preview/bundle URLs. top-level-evidence.json and theme-template-sample-evidence.json preserve the broader site surfaces. booking.report.md records date/form/dialog findings and the Origin UI unavailable-date evidence limit. coverage-counts.json records the coverage accounting.



## Delivered evidence

Coverage accounting: [21st-coverage.json](evidence/21st-coverage.json). Selected publisher previews are in [references/](references/); larger category/metadata inventories are retained in the working audit directory. This reference report documents reviewed alternatives, not installed components.
