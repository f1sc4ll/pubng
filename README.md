# PubWeb

A lightweight, mobile-first WordPress theme for ad-monetized publishers. Built for one job: **paint the page and the ads as fast as possible**, because for an arbitrage publisher time-to-first-ad is revenue.

Designed from a teardown of 8 production ad-arbitrage sites (GAM/GPT + ActView stack). It keeps what they do well (client-side ad injection, inlined critical CSS, ad-origin preconnect/preload, system fonts, speculation rules) and fixes what they miss (most ship no `Article` schema — PubWeb does, automatically).

## Why it's fast

| Technique | Implementation |
|-----------|----------------|
| No render-blocking CSS | Critical CSS inlined in `<head>`; full stylesheet loaded async (print-media swap) |
| Ads warmed early | `preconnect` to ad origins + optional `preload` of `gpt.js` printed at `wp_head` priority 0 |
| Zero web-font latency | System font stack by default (toggle in settings) |
| No frontend jQuery | One tiny deferred vanilla JS file (nav + search only) |
| No layout shift | Ad slots render as space-reserved wrappers (`min-height` from declared sizes) |
| Cheap extra pageviews | WordPress Speculation Rules (prefetch on hover) |
| Less core bloat | Emoji script, oEmbed discovery, generator/RSD/wlw links removed |

The theme **ships zero ad markup** in the static document. A single external loader script (e.g. ActView or a GAM bootstrap) defines and injects the real GPT units into the reserved wrappers — exactly the decoupled model the reference network uses.

## Automatic schema (JSON-LD)

Emitted per page type as a linked `@graph`, and **automatically skipped** when Yoast / Rank Math / SEOPress / AIOSEO is active (no duplicate markup):

- Every page: `Organization` (or `NewsMediaOrganization`) + `WebSite` + `SearchAction`
- Single post: `Article` / `BlogPosting` / `NewsArticle` (configurable) + `WebPage` + `BreadcrumbList` + `Person`
- Category / tag / date archive: `CollectionPage` + `BreadcrumbList`
- Author archive: `ProfilePage` + `Person`

## AI-friendly discovery

A generated `/llms.txt` ([llmstxt.org](https://llmstxt.org) convention) gives LLM crawlers a clean, link-first map of the site — sections + recent articles + sitemap — without forcing them to parse ad-heavy HTML. Advertised via a `<link rel="alternate">` in `<head>` and cached for an hour.

## Configuration API (`pubweb/v1`)

The theme is **editable headlessly by an AI agent** through a token-authenticated REST API — no wp-admin, no native WP file editor. The editable surface is a structured **settings tree** (layout, ads, schema, SEO, custom code), never raw files: writing PHP from a request would be a remote-code-execution backdoor, so that door does not exist. The settings tree drives every template, so it is expressive enough to restyle the whole site.

### Enable it

The API is **off until a token is configured**. Either pin a token in `wp-config.php` (recommended):

```php
define( 'PUBWEB_AI_TOKEN', '…64 hex chars…' ); // openssl rand -hex 32
```

…or rotate one at runtime (stored only as a SHA-256 hash):

```bash
# First call must be authorized; bootstrap by setting the constant once,
# then rotate to a runtime token and remove the constant if you prefer.
curl -X POST https://site.com/wp-json/pubweb/v1/token/rotate \
  -H "Authorization: Bearer $PUBWEB_AI_TOKEN"
```

### Security model

- Token compared in **constant time** (`hash_equals`); caller is treated as admin-trust.
- API returns **503** until a token exists (secure by default).
- **Rate limited** per IP (60 req/min) → **429**.
- Every write is recorded in a capped **audit log** (`GET /audit`).
- No filesystem, no SQL, no arbitrary code endpoints.

### Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/health` | Theme/WP/PHP versions, active state, ad/SEO status |
| GET | `/settings` | Full settings tree |
| GET | `/settings/schema` | Machine-readable `{type, default}` of every editable key |
| PATCH | `/settings` | Partial update (unknown keys rejected, values type-coerced) |
| GET / PUT | `/ads` | Read / replace ad config (network code, loader URL, slots) |
| GET / PUT | `/custom-code` | Head/footer HTML (ad/analytics tags) + custom CSS |
| GET | `/audit` | Recent API actions |
| POST | `/token/rotate` | Rotate the runtime token (returns plaintext once) |

All routes require `Authorization: Bearer <token>` (or `X-PubWeb-Token: <token>`).

Example — switch to a 3-column grid and enable ads on a new GAM network:

```bash
curl -X PATCH https://site.com/wp-json/pubweb/v1/settings \
  -H "Authorization: Bearer $PUBWEB_AI_TOKEN" -H "Content-Type: application/json" \
  -d '{"layout":{"posts_columns":3},"ads":{"enabled":true,"gam_network_code":"21885211673"}}'
```

## Self-hosted updates (JSON / S3)

Disabled by default. When V1 is published, point the theme at a JSON manifest on S3/CDN:

```bash
curl -X PATCH .../pubweb/v1/settings -H "Authorization: Bearer $TOKEN" \
  -d '{"updater":{"enabled":true,"manifest_url":"https://cdn.example.com/pubweb/manifest.json"}}'
```

Manifest shape:

```json
{
  "version": "1.1.0",
  "download_url": "https://cdn.example.com/pubweb-1.1.0.zip",
  "requires": "6.2",
  "requires_php": "8.1",
  "tested": "6.9",
  "changelog": "…"
}
```

WordPress then shows the update in Appearance → Themes like any other. Only HTTPS package URLs are trusted.

## Requirements

- WordPress 6.2+
- PHP 8.1+

## Ad slot positions

Declared slots (via the API) render as reserved-space wrappers at: `header`, `before_content`, `in_content` (auto-injected after the Nth paragraph), `after_content`, `sidebar`, `footer`, and `anchor` (a dismissible sticky bottom bar — high viewability, zero CLS).

## What's not in V1

- No visual ad-block editor (an Ad Inserter replacement) — slots are declared via the API. Planned for a later version.

## File map

```
pubweb/
├── functions.php          bootstrap + module loader
├── inc/
│   ├── settings.php        single source of config truth (typed tree)
│   ├── setup.php           supports, menus, sidebars, image sizes
│   ├── assets.php          critical CSS inline + preconnect/preload + async CSS
│   ├── performance.php     bloat removal, speculation rules, lazy
│   ├── template-tags.php   template helpers
│   ├── schema.php          JSON-LD per page type
│   ├── seo.php             meta/OG/canonical (plugin-aware)
│   ├── ads.php             slot sanitizer + reserved wrappers + anchor + loader
│   ├── ai-discovery.php    /llms.txt for AI crawlers
│   ├── class-ai-auth.php   token auth, rate limit, audit
│   ├── class-ai-rest.php   pubweb/v1 REST controller
│   └── class-updater.php   JSON/S3 theme updater (gated)
├── template-parts/         card, content-single, content-none
├── assets/css|js/          critical.css, main.css, pubweb.js
└── *.php                   header, footer, index, single, page, search, 404, …
```
