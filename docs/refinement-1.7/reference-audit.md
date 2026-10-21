# Focused 21st.dev reference audit for JOYRENT 1.7

Checked 2026-10-03. This is a targeted study of process, footer contacts, hero CTA and product-image motion for the existing black/white/amber JOYRENT design. It is not an exhaustive catalog audit. Six current category indexes were read, eleven exact component pages were checked, and ten public static previews were visually inspected. Static previews establish composition; live interaction, motion timing and responsive behavior were not tested. No production edits, registry installs or paid tools were used.

## Transferable shortlist

| Area and exact component | Published license / npm dependencies | What the inspected preview supports | Suitable JOYRENT adaptation |
| --- | --- | --- | --- |
| [Process Timeline](https://21st.dev/@shadcnui-blocks/components/timeline-05) | `no-license`; `lucide-react` | A single thin vertical rail, small circular step nodes, open title/copy rows, no enclosing card boxes. Some sample stages have completed check marks. | Use an independently written ordered list with 01–03, short rental copy and one quiet connector. Open rows on black; muted white text and one restrained amber cue. Avoid the sample's completed-state checks because these are explanations, not customer progress. Desktop may keep three open columns connected by one line; mobile uses the vertical rail. |
| [Minimal Two-Column Footer](https://21st.dev/@ln-dev7/components/footer-17) | MIT; metadata npm list empty | Brand/tagline at left, a compact unboxed link stack at right, one fine rule and a quiet copyright row. | Keep JOYRENT at left and show configured Phone / Telegram as labeled text links with the existing line icons. Low-emphasis gray/white, amber on hover/focus. No separate contact cards or oversized contact pills. |
| [FlowButton](https://21st.dev/@xubohuah/components/flow-button) | MIT; `lucide-react` | A simple rounded button with a clear short label and trailing arrow. The static image does not prove its hover animation. | One solid warm-white or muted amber primary CTA, comfortable horizontal padding, plain rental/date label, trailing arrow. A tiny arrow shift is sufficient; keep the label fixed. |
| [Texture Button](https://21st.dev/@cult-ui/components/texture-button) | MIT; `@radix-ui/react-slot`, `class-variance-authority` | Soft top-edge highlight and shallow depth across simple button variants. Some demo colors are blue/red. | Borrow only subtle surface depth for the primary CTA: quiet inset highlight, tiny pressed movement, strong readable contrast. Use JOYRENT amber/white and omit the blue/red demo colors, glow and moving shine. |
| [Reveal Image Mask](https://21st.dev/@daiwiikharihar/components/reveal-image-mask) | Unknown (empty HTML license); `framer-motion` | Editorial photograph inside a restrained but clearly shaped mask. Metadata describes scroll-driven bloom into full frame; timing was not observed. | Borrow the idea of one deliberate image arrival, not the large mask/bloom. Retain full console/controller visibility and image dimensions. A short opacity/translation reveal can use JOYRENT's existing motion library. |
| [Parallax Floating / Floating Card](https://21st.dev/@cnippet-dev/components/parallax-floating/floating-card) | `no-license`; `motion` | Quiet overlapping layers in a hover-depth composition; the preview includes colored dots/blobs and a card. Metadata describes cursor-based parallax; live response was not tested. | Independently move only the existing photo/light layers by a few pixels. Keep the original product artwork, remove decorative dots/blobs/cards and avoid moving page copy or layout. Disable pointer movement on touch and honor reduced motion. |

These are design principles, not a recommendation to import packages. JOYRENT already has icons and Framer Motion, so the process/footer/button can be native HTML/CSS and image motion can use existing code. Entries with no/unknown license are reference-only; their component source should not be copied. An empty npm metadata object does not prove there are no registry/transitive dependencies.

## Inspected public preview evidence

- Process Timeline: https://cdn.21st.dev/shadcnui-blocks/timeline-05/default/preview.1789972934699-4d7c5529-cf3f-4dbc-8164-ccdd84b0c9dc.png
- Minimal Two-Column Footer: https://cdn.21st.dev/ln-dev7/footer-17/default/preview.1788991572948-a215dfe7-1375-461b-8406-0ca12d832f8d.png
- FlowButton: https://cdn.21st.dev/user_2vl1Dn19f2uJMuYVfUEcAh5qqyv/flow-button/default/preview.1746631996530.png
- Texture Button: https://cdn.21st.dev/user_cult_ui/texture-button/default/preview.png
- Reveal Image Mask: https://cdn.21st.dev/user_332oxzW8MyPod4IDIdKq9rbPYuk/reveal-image-mask/default/preview.1785744283195.png
- Parallax Floating / Floating Card: https://cdn.21st.dev/user_36Tbt0v8JdD4jEycmBFhR8tojnR/parallax-floating/floating-card/preview.1787333855127.png

Structured shortlist metadata, exact URLs and inspection limits are saved in `reference-evidence.json`.

## Alternatives deliberately filtered out

[Vertical How It Works Timeline](https://21st.dev/@ln-dev7/components/how-it-works-02) declares MIT/lucide-react, but its actual inspected preview has four large outlined cards. Its connecting rail is useful; its card framing repeats the problem the user identified. [Process Pillars](https://21st.dev/@ankitsharma2615/components/process-pillars) has an unknown license/framer-motion and shows five blue partially filled columns; the chart-like progression is a poor fit for rental explanation. These were inspected and excluded as whole compositions.

[Simple Centered Footer](https://21st.dev/@ln-dev7/components/footer-16) declares no-license and shows a centered nav plus large circular social controls. [Efferd Minimal Footer](https://21st.dev/@efferd/components/minimal-footer) has an unknown license/lucide-react and shows a denser company/resources footer with six social squares. Both confirm that contact links can stay visually quiet, but JOYRENT needs two labeled contact channels rather than a social grid. The MIT two-column footer is the cleaner starting principle.

## Concrete refinement direction

Replace the three outlined process cards with one coherent open sequence. Keep headings and body copy short, with numbers doing the sequencing and spacing doing the grouping. Let the separate delivery block remain the place for geographic/price detail.

Make Phone and Telegram ordinary identifiable footer links with good touch targets and visible focus, visually subordinate to the primary rental CTA. Show only the operator's configured contacts; do not import publisher/sample contact information.

Give the hero one clear solid CTA whose label and arrow feel connected. Use subtle material depth from Texture Button and the simple arrow composition from FlowButton. Keep other hero links quieter.

For product motion, prefer a one-time arrival plus a slow small float of the photo/light layer. A proposed range is roughly 2–5px travel with less than half a degree rotation; this is a JOYRENT design suggestion, not a measured property of any reference. Pause offscreen, remove nonessential movement for reduced-motion preferences, and preserve immediate image/headline/CTA visibility. No masks that crop the product, scroll locking, shaders, animated metrics or neon decoration are needed.
