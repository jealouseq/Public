# JOYRENT Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Build the selected premium Ukrainian JOYRENT design as an installable WordPress theme with a WooCommerce rental-request plugin.

**Architecture:** PHP theme mounts a compiled React/TypeScript frontend. A separate plugin owns tariffs, game records, settings, validation and WooCommerce request creation. Unknown deposit, delivery and extra-controller amounts remain pending confirmation; no automatic availability or payment claims.

**Tech Stack:** WordPress, WooCommerce, PHP 8.2+, React, Vite, TypeScript, Tailwind 4, Framer Motion, Phosphor icons; local Unbounded and Manrope fonts.

**Spec:** ../specs/2026-10-02-joyrent-design.md

## Global Constraints

- Ukrainian interface; premium near-black design based on homepage-01.png.
- PS5 tariffs: 1/3/7/30 days = 600/1400/2500/6000 UAH.
- PS4 tariffs: 3/7/30 days = 750/1200/2500 UAH.
- WordPress + WooCommerce remain the platform. Next.js is unnecessary for the supplied image effect.
- Do not invent delivery geography, fees, deposit, inventory, game availability or business contacts.
- Mobile 360/390/430/768 and desktop 1280/1440; reduced-motion support.

## Review Focus

- Invalid and past dates must fail; daylight-saving boundaries must preserve calendar-day semantics.
- Changing PS5 to PS4 must remove the unsupported one-day selection.
- WooCommerce order totals must be computed on the server, ignoring browser-submitted prices.
- Incomplete delivery/deposit configuration must not turn unknown charges into zero or enable payment.
- Public requests must resist duplicate submissions, arbitrary game IDs and accidental PII exposure.

## Task 1: Catalog and rental domain

Files: wordpress/joyrent-rentals/data/catalog.json; src/lib/rental.ts; src/lib/rental.test.ts; wordpress/joyrent-rentals/includes/domain.php; tests/php/domain-test.php.

Interfaces: ConsoleId = ps5 | ps4; quote(console, days) returns the configured tariff; addRentalDays(date, days) returns an ISO calendar date. PHP JR_Domain validates the same payload and tariffs.

- [x] Write tests for all seven totals, unsupported PS4 one-day requests, invalid dates, leap day and October DST boundary.
- [x] Run npm test and PHP domain tests; confirm failure before implementation.
- [x] Implement shared catalog and matching TS/PHP validation.
- [x] Run both suites and commit the working domain.

## Task 2: Frontend and selected visual target

Files: src/App.tsx; src/sections/*; src/styles.css; components/ui/scroll-reveal-image.tsx; src/lib/api.ts; wordpress/joyrent/assets/images/*.

Interfaces: Frontend uses Catalog and JR_Domain-equivalent calculations. window.JOYRENT supplies API endpoint, asset base, contact and delivery settings. POST request payload contains customer details, console, term, dates, controllers, requested game IDs, method, address and consent.

- [x] Build hero, tariff switch, game carousel/detail dialog, rental form, contents, steps, delivery, FAQ and final CTA.
- [x] Adapt ScrollRevealImage to a normal responsive image; honor reduced motion.
- [x] Install self-hosted fonts and actual generated assets; keep cards free of made-up official branding.
- [x] Verify mobile navigation, tariffs, dates, filters, dialog and consent in Chromium. No fake success if backend is absent.
- [x] Run TypeScript/build checks and commit.

## Task 3: WordPress and WooCommerce integration

Files: wordpress/joyrent/*.php; wordpress/joyrent/style.css; wordpress/joyrent-rentals/joyrent-rentals.php; wordpress/joyrent-rentals/includes/{admin,rest,orders,games}.php.

Interfaces: GET /joyrent/v1/catalog exposes public catalog/config; POST /joyrent/v1/requests creates a WooCommerce order in jr-request status; response contains only request reference and server rental amount.

- [x] Install WordPress and WooCommerce in local Docker containers; keep credentials in ignored work files.
- [x] Write integration tests for valid requests, price tampering, invalid dates, missing consent and missing delivery address; observe red.
- [x] Add theme enqueue/boot settings, plugin settings/importer, game CPT/meta, server validation, rate limit, honeypot and idempotency.
- [x] Use WooCommerce CRUD for HPOS compatibility; never auto-confirm availability or mark payments paid.
- [x] Run real HTTP request tests and verify resulting WooCommerce order fields and status; commit.

## Task 4: Verification, packages and handoff

Files: scripts/{optimize-assets,package}.mjs; README.md; docs/INSTALL.md; design-qa.md; releases/*.

- [x] Review selected reference against browser screenshots at matching desktop viewport; repair meaningful visual issues.
- [x] Check 360/390/430/768/1280/1440 widths, keyboard focus, reduced motion, forms and console errors.
- [x] Obtain independent whole-branch code review; fix important findings with regression tests.
- [x] Build theme/plugin ZIPs and static visual preview; confirm archives contain compiled code and assets.
- [x] Push feature branch and create a reviewable GitHub PR with verification and configuration limits.

Execution authorized by the user's instruction to choose the better mockup and build it. Proceed continuously without another design/plan approval request.

Handoff: https://github.com/jealouseq/Public/pull/1 — theme/plugin/preview ZIPs, screenshots, installation and verification reports. Public WordPress deployment awaits hosting and actual store settings.
