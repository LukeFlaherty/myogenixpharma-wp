# Redesign Playwright Audit - 2026-08-08

## Scope Checked

Playwright checks were run against production on desktop `1440x1000` and mobile `390x844`.

Category/program URLs checked:

- `/weight-management/`
- `/mens-health/`
- `/sexual-health/`
- `/wellness/`
- `/womens-health/`
- `/product-category/weight-loss/`
- `/product-category/mens-health/`
- `/product-category/sexual-health/`
- `/product-category/peptides-longevity/`
- `/product-category/womens-health/`
- `/product-category/uncategorized/`

Product URLs checked:

- `/product/hcg/`
- `/product/compound-retatrutide/`
- `/product/epithalon/`
- `/product/motsc/`
- `/product/bpc/`
- `/product/compound-tirzepatide/`
- `/product/compound-semaglutide/`
- `/product/klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg/`
- `/product/tesamorelin-ipamorelin/`
- `/product/cjc1295-ipamorelin/`
- `/product/2606/`
- `/product/compound-oral-tadalafil/`
- `/product/compound-sildenafil/`
- `/product/compound-injectable-nad/`
- `/product/compound-injectable-sermorelin/`
- `/product/compound-injectable-glutathione/`
- `/product/testosterone/`

Reference screenshots available:

- Program categories: weight management, men's health, peptides, sexual health.
- PDP: testosterone only.
- No exact screenshot references were found for HCG, GLP-1 PDPs, peptide PDPs, tadalafil/sildenafil PDPs, women's health category, or uncategorized category.

## High-Priority Discrepancies

1. `/product-category/womens-health/` is still the default Woo archive.

- Desktop and mobile both render a white WooCommerce archive shell with breadcrumb text and `No products were found matching your selection.`
- It does not receive `grunge-redesign-page` / `grunge-program-page` classes.
- This is in the stated category scope even though there is no exact design screenshot for the Woo archive.
- Recommended fix: map the empty women's health product category to the same coded grunge category template style as `/womens-health/`, with an intentional empty-state or CTA.

2. `/product-category/uncategorized/` is still the default Woo archive and throws an Elementor frontend error.

- Desktop and mobile both render the default white Woo archive.
- Console error: `ReferenceError: elementorFrontendConfig is not defined`.
- There is no design screenshot for this category and it has zero products.
- Recommended decision: either redirect/noindex this route, or give it a coded grunge empty-category shell and ensure Elementor frontend scripts are dequeued there too.

3. Non-testosterone PDPs are not yet at the same screenshot-level grunge treatment as the testosterone PDP.

- Testosterone has the closest matching source PDP structure.
- HCG, GLP-1 products, peptide products, tadalafil, and sildenafil still use the older generic PDP composition: grunge-ish hero, legacy trust cards, white configurator/summary panels, and older `Common questions`/`Explore More Treatment Lines` sections.
- Because the screenshot set only includes testosterone, this is a design-system parity issue rather than a one-to-one screenshot mismatch for every product.
- Recommended fix: port the shared Next PDP visual system across all PDP branches, while preserving each product branch's Woo variation/add-to-cart logic.

4. Non-testosterone PDPs have horizontal overflow, especially on mobile.

- Mobile overflow was detected on every non-TRT PDP.
- The representative HCG overflow source is the product scroller cards (`.hp-card`) far off-canvas around the `Explore More Treatment Lines` section.
- Desktop overflow also appears on HCG, GLP-1, tadalafil, and sildenafil PDPs.
- Recommended fix: constrain horizontal product scrollers with `overflow-x: auto` on the scroller only and prevent the body/page from expanding. Re-test all PDPs after the shared scroller CSS fix.

## Screenshot-Level Category Notes

Designed category pages mostly pass the high-level sequence:

- grunge nav
- hero
- care strip
- product/option cards
- how-it-works
- final CTA
- grunge footer

Observed differences against the provided category screenshots:

- Production chat widget overlays the lower-right content area during visual QA. The source screenshots show a demo banner instead. This makes live screenshots look less aligned even when layout is close.
- Footer/company links differ from source screenshots. Source includes `About` and `Contact`; production currently emphasizes `Concierge` because `/about/` and `/contact/` are not live production routes.
- `/womens-health/` is coded grunge but has no matching provided category screenshot, so it needs an intentional design decision rather than a strict parity comparison.

## Testosterone PDP Notes

Compared against `03-products/*/mens-health__testosterone__full.png`.

What is close:

- Grunge nav, hero, bottle placement, build-plan panel, trust strip, plan cards, process cards, support FAQ, and footer sequence are present.
- Desktop has no meaningful horizontal overflow.
- The legacy hero bullets/cards are hidden on TRT.

Differences to address:

- Mobile hero title is very tight at 390px. DOM metrics show it technically fits, but visually it can read as clipped at the right edge. Source has slightly more breathing room.
- Live configurator starts with an `Eligibility` state picker before `1. Vial Strength`; the source screenshot starts directly with `1. Vial Strength`. State gating may be required for production, but visually this changes the hierarchy.
- Live summary total is `$165` after state detection; source screenshot shows `$228`. Confirm whether this is intentional production pricing/lab-fee logic or a copy/summary mismatch.
- Live CTA says `Schedule Bloodwork`; source screenshot says `Continue to Evaluation`. Confirm desired production CTA wording.

## Console / Runtime Notes

- Most product console errors are the external Google Pay payment manifest icon decode issue:
  - `Failed to download or decode a non-empty icon for payment app with "https://pay.google.com/gp/p/web_manifest.json" manifest.`
  - This appears intermittently on product pages and is not from the theme redesign code.
- `/product-category/uncategorized/` has a real non-payment frontend error from Elementor, listed above.
- PHP linting remains clean for the key edited templates.

## Raw Audit Artifacts

The Playwright screenshots and raw logs were temporary local QA artifacts and were cleaned after this note was written. Re-run the same URL set if fresh evidence is needed before implementing a fix pass.
