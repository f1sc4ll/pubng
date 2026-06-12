# PubWeb — Layout Catalog (8 reference ad-arb publishers)

Synthesis of a deep teardown (homepage + article HTML/CSS) of: informacaoagora,
buzzblitz, gofrix, jrfinancas, seucartaodecredito, sofiotheque, como-funciona,
creditoefinancas. This is the design brief for taking PubWeb past "generic WP
blog" and the master list of what the admin panel / API should expose.

## The 3 things that make them NOT look like a default theme

1. **Token-driven 2-color identity.** Each site is essentially an 8-slot palette
   (Blocksy) or a `:root` block of CSS vars (custom themes) — one accent reskins
   chips, buttons, nav drawer, pagination, footer. Disciplined: one font in 1–2
   weights, one radius (3–12px), one spacing rhythm (15/30px).
2. **Homepage is a curated landing, not a reverse-chron loop.** Color-blocked
   full-width bands, big 30–56px headlines with accent-colored words, icon/benefit
   cards, testimonials, multi-geometry feeds (overlay hero + text list + scroll-snap
   carousel + 2/3-col mixed). The post grid is often demoted to one "blog" section.
3. **The article is an ad funnel.** Narrow centered column (730–1000px), featured
   image frequently suppressed, NO sidebar, ONE labeled "money slot"
   ("Advertisement"/"Anúncios"/"Publicidad", 10px uppercase) reserved at
   `min-height: 320–400px` right under the title before ¶1, then more every 2–3
   paragraphs. Native "card-ad" CTA components, a related-posts band, a styled
   conversion button ("you will be redirected"), and a mega-disclaimer footer.

## Convergent specifics (implement these)

| Element | Pattern across sites |
|---|---|
| **Ad slot** | Labeled wrapper, `min-height 320–400px` (anti-CLS), 10px uppercase label; unit naming `{site}_{device}_{position}[_geo]` e.g. `scc_desktop_top`, `sfq_desktop_content_1_us`; positions: top (pre-¶1), content_1 (~¶3), content_2 (~¶7), anchor, interstitial |
| **Homepage ads** | Zero — lander stays fast; monetize article pages |
| **Cards** | Overlay "poster" (scrim `rgba(0,0,0,.5)`, white title, underline CTA), classic (2:1 or 3:2 image + title + excerpt), horizontal minis (135×90), scroll-snap/Swiper carousel on mobile |
| **Category chip** | Small uppercase colored label, stable color per category |
| **Section heading** | Uppercase tracked + accent bar OR chip-label ("Latest"/"Últimas") |
| **Header** | Logo + nav (uppercase 600–800), search (icon→slide-out/modal), off-canvas mobile drawer (often accent-colored), sticky-shrink (54→41px), reading-progress + scroll-state on articles |
| **Footer** | Dark, multi-column (`2fr 1fr 1fr`), **legal/advertiser disclaimer as a first-class row**, company + CNPJ, read-more clamp |
| **Interactive** | Search modal, back-to-top, Swiper carousels, FAQ accordion, AOS fade-up on landing, numbered pagination (not load-more) |
| **Typography** | System fonts OR one self-hosted woff2 (preloaded); base 14–17px; big bold display titles |
| **Conversion** | End-of-article CTA button (gold/green), author box, related band |

## Master configurable-knobs list (admin panel / `pubweb/v1` settings)

Shipped in v1.1 ✓ · planned ▢

- ✓ Accent + header/footer/body background colors; logo max width
- ✓ Card style (classic / overlay); columns 2/3; category chip on/off
- ✓ Section heading on/off; sticky header + shrink; back-to-top; reading progress
- ✓ Ad: enable, GAM network, loader URL, label text, homepage on/off, slot positions
- ✓ Schema type, SEO toggles, custom head/footer/CSS
- ▢ **Full 8-slot palette** (primary/hover/text/heading/border/subtle-bg/off-white/white)
- ▢ **Homepage section builder** — ordered bands (hero, icon row, benefit carousel, post grid, testimonials) each with layout + source
- ▢ **Hero/landing block** — eyebrow + headline w/ accent-colored word, 2 CTAs, image, AOS toggle
- ▢ **Article conversion CTA** (button color/label/redirect) + author box + related band columns
- ▢ **Footer disclaimer rows** (legal notice / advertisers / editorial) + read-more clamp + company/CNPJ
- ▢ **Search modal / slide-out** + off-canvas mobile drawer color
- ▢ Content max-width per template; in-content list/blockquote accent colors
- ▢ Per-device/geo ad unit naming + min-height reserve; native "card-ad" component
- ▢ Self-hosted webfont upload + preload; typography scale (H1–H6)

## Per-site signature (quick reference)

- **informacaoagora** (`mundohoje`): indigo/teal/yellow trio, serif editorial, flat borderless cards, reading-progress + scroll-morph header.
- **buzzblitz** (`activeview`): 9 `:root` tokens, dark overlay poster cards (white 24px titles, underline CTA), tracked section headings w/ SVG accent bar.
- **gofrix** (`blossom-pin`): portrait owl carousel hero (375×450), JS masonry cards (radius 12, layered shadow), sticky reading header, back-to-top.
- **jrfinancas** (`oceanwp`): 3-color identity (gray header / blue footer / orange bar), sticky-shrink header, equal-height flat grid, numbered pagination.
- **seucartaodecredito** (`blocksy`): 8-slot blue palette, transparent header + white-logo-on-dark-hero, landing page, one `scc_desktop_top` money slot, dark footer disclaimer rows.
- **sofiotheque** (`ddmp-theme`): panel-driven CSS vars (header `#ad4141`, font), Swiper benefits band + mobile post carousel, yellow-bordered lists, native card-ads, dark footer + CNPJ.
- **como-funciona** (`wmdt`): 15 `--lt-*` vars (black + gold `#ddc152`), scroll-snap carousels, off-canvas gold drawer, gold 311×63 CTA button, footer read-more clamp.
- **creditoefinancas** (`blocksy`+greenshift): 8-slot green palette, Greenshift landing (55px hero + AOS), Kadence 3-post teaser, FAQPage schema, hreflang multi-geo, "Advertisement" money slot.

_Compiled 2026-06-12 via 4 parallel Fable agents. Source raw catalogs available in session transcript._
