# PubWeb — Roadmap & Backlog

What V1 deliberately left out, and ideas worth doing next. Grounded in the
teardown of 8 production ad-arb sites (informacaoagora, buzzblitz, gofrix,
jrfinancas, seucartaodecredito, sofiotheque, como-funciona, creditoefinancas).

Status legend: 🔴 not started · 🟡 partial/stubbed · 🟢 done in V1 (reference)

---

## Deferred from V1 (decided, not bugs)

- 🔴 **Visual ad-block editor (Ad Inserter replacement).** The headline V2
  feature. A wp-admin (or API-driven) UI to place/preview ad units between blocks,
  by post type, category, device, and position — without editing templates.
  V1 only *declares* slots via `ads.slots` in the API. This is the big build.
- 🔴 **`screenshot.png` is a generated placeholder.** Replace with a real
  rendered screenshot once a demo site is up (1200×900).
- 🟡 **Ad loader is BYO.** V1 injects an external loader URL (`ads.loader_script_url`)
  and reserves slots, but ships no GPT bootstrap of its own. Either keep relying on
  ActView-style loaders or build a first-party GPT define/display script (see below).

---

## High-value next (do these first)

- 🔴 **First-party server-side GTM proxy** (seen on gofrix: `api.gofrix.com` →
  Stape). Adblock-resistant, first-party tracking + ad delivery. Add a settings
  block + docs for proxying the tag through a site subdomain.
- 🔴 **Own GPT loader (`assets/js/ads.js`)** as an alternative to BYO: read
  `window.pubwebGAM` + the slot wrappers (`[data-pw-slot]`), `defineSlot` per
  device, lazy-render below-the-fold via IntersectionObserver, optional refresh/
  rebid. Slot naming already follows `{device}_{position}` from the refs.
- 🔴 **CMP / consent wiring** (Google Funding Choices or a TCF v2 CMP). Every ref
  site had one; required for EU traffic and AdSense/GAM policy. Gate ad init on
  consent. Add `ads.cmp` settings.
- 🔴 **Multipage article navigator** (gofrix `mamute`): optional auto-split of long
  posts into paginated sub-pages to multiply pageviews/impressions. Honor
  `wp_link_pages` + a setting `layout.auto_paginate_words`.
- 🔴 **Lazy-render anchor + in-content slots** on scroll (IntersectionObserver) to
  protect LCP while keeping viewability — pairs with the own-GPT-loader item.

---

## SEO / AI-friendly backlog

- 🔴 **XML sitemap awareness.** Currently relies on core/Yoast sitemap. Confirm
  `/sitemap.xml` link in `llms.txt` resolves; optionally emit a lightweight sitemap
  when no SEO plugin is active.
- 🔴 **hreflang multi-geo** (creditoefinancas ran `/mx/ /co/ /es/ /br/…` with full
  hreflang). Add helper output for multilingual/multi-country arbitrage funnels.
- 🔴 **`FAQPage` / `HowTo` schema** opt-in for how-to/finance content (rich results).
- 🔴 **`Speakable` + author `sameAs`** to strengthen E-E-A-T signals.
- 🟢 Article/CollectionPage/ProfilePage/BreadcrumbList JSON-LD — done in V1.
- 🟢 `/llms.txt` discovery — done in V1.

---

## Performance backlog

- 🔴 **Optional self-hosted preloaded webfont** path (como-funciona preloads its
  own woff2). V1 defaults to system fonts; add a setting to register + `preload` a
  bundled woff2 for sites that need brand type without Google Fonts latency.
- 🔴 **Critical-CSS build step** (PostCSS/critical) to auto-extract per-template
  critical CSS instead of the hand-curated `critical.css`. Keep zero-build install
  but offer an optional `npm run build`.
- 🔴 **`fetchpriority`/`preload` for the LCP image on archives** (only single +
  featured set it today).
- 🟢 Inline critical CSS, async main CSS, preconnect+preload ad stack, deferred JS,
  speculation rules, bloat removal — done in V1.

---

## API / tooling backlog

- 🔴 **`POST /ads/slots` granular CRUD** (add/remove a single slot) instead of full
  `PUT /ads`. Nicer for an AI agent making incremental edits.
- 🔴 **`GET /preview`** returning rendered HTML of a sample post with current
  settings, so an agent can verify a change without a browser.
- 🔴 **Webhook on settings change** (notify pubweb.ai control plane).
- 🔴 **Per-route capability scoping** for the token (read-only vs read-write).
- 🔴 **WP-CLI command** `wp pubweb token`/`settings` for headless provisioning.
- 🟡 **Updater is gated off.** When V1 ships, publish a manifest on S3/CDN, set
  `updater.enabled` + `manifest_url`. Consider signing the package (checksum in
  manifest) before trusting auto-update.

---

## Admin UX backlog (currently API-only)

- 🔴 **Customizer / settings page** mirroring the settings tree for humans (the AI
  uses the API; an operator may want a panel). Keep it thin — the API stays the
  source of truth.
- 🔴 **Per-category / per-post ad overrides** (foundation for the V2 ad editor).

---

## Known limitations / gotchas (read before extending)

- **Custom head/footer code is stored verbatim** (must carry `<script>` ad tags) —
  admin-trust only. Don't expose those endpoints to lower-trust callers.
- **No filesystem endpoint by design** — never add raw file/PHP writes to the API
  (RCE). Extend behavior through the settings tree + hooks instead.
- **`array_is_list()` ⇒ PHP 8.1+ floor.** Don't lower it without a polyfill.
- **`home.php` is the posts index**, never the static front page (that's `page.php`).
- **In-content ad injection splits on `</p>`** — posts built entirely of blocks
  without paragraphs won't get in-content slots; add block-aware injection if needed.
- **Anchor/sticky slot CSS lives in `main.css`** (async) — acceptable since it's a
  fixed overlay, but verify no flash on very slow connections.

---

_Last updated: 2026-06-12 (V1.0.0)._
