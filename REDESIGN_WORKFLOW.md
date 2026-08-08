# Myogenix Redesign Workflow

Last updated: 2026-08-08

## Goal

Port the approved redesign from the Next.js frontend clone into the live WordPress/WooCommerce child theme at production quality.

This is a production WordPress.com Business site. Staging is intentionally out of scope because it is not caught up with production ordering functionality. Work goes directly through `main`, then live QA happens on the production URL after deploy.

## Local PHP / WordPress Tooling

Installed through Homebrew on 2026-08-08:

- PHP CLI: `/opt/homebrew/bin/php` (`php` formula, currently PHP 8.5.x)
- WordPress-friendly PHP CLI: `/opt/homebrew/opt/php@8.4/bin/php` (`php@8.4`, keg-only)
- WP-CLI: `/opt/homebrew/bin/wp`

Use PHP linting before committing WordPress template changes:

```bash
php -l functions.php
php -l woocommerce/content-single-product.php
php -l front-page.php
php -l page-program-category.php
php -l template-parts/site-header.php
php -l template-parts/site-footer.php
```

For WP-CLI commands, prefer PHP 8.4 by prefixing PATH so WP-CLI does not run under the bleeding-edge Homebrew `php` formula:

```bash
PATH="/opt/homebrew/opt/php@8.4/bin:/opt/homebrew/opt/php@8.4/sbin:$PATH" wp --info
```

Notes:

- No PHP-FPM or background service is needed for linting or WP-CLI inspection.
- This local checkout does not currently include a usable `wp-config.php`/database connection, so WP-CLI may not be able to inspect posts/products unless a complete local WordPress runtime is added later.
- `php -l` remains useful even without a local database because it catches syntax errors in PHP templates before code is pushed to production.

## Source Design References

- Frontend clone: `/Users/lukeflaherty/dev/client/myogenixpharma`
- Live preview: `https://myogenix-pharma.vercel.app/`
- Redesign assets: `/Users/lukeflaherty/dev/client/myogenixpharma/public/assets/grunge-redesign`
- Screenshot source folder: `/Users/lukeflaherty/dev/client/myogenix-assets/Design Screenshots/Myogenix Pharma Grunge Redesign R1`
- Screenshot image output folder: `/Users/lukeflaherty/Downloads/Myogenix Pharma Grunge Redesign R1`
- Figma/design source: none
- WordPress theme repo: `/Users/lukeflaherty/dev/client/myogenixpharma-wp/wp-content/themes/myogenix-theme`
- Production site: `https://myogenixpharma.com`

## Screenshot References

The redesign screenshot set was captured from `https://myogenix-pharma.vercel.app` on 2026-08-07.

Source metadata:

- `/Users/lukeflaherty/dev/client/myogenix-assets/Design Screenshots/Myogenix Pharma Grunge Redesign R1/README.md`
- `/Users/lukeflaherty/dev/client/myogenix-assets/Design Screenshots/Myogenix Pharma Grunge Redesign R1/capture-summary.json`

Actual screenshot images:

- `/Users/lukeflaherty/Downloads/Myogenix Pharma Grunge Redesign R1`

Captured groups:

- `01-pages`: homepage, about/contact, affiliate, demo, legal, and portal/login pages
- `02-program-categories`: weight management, men's health, peptides, and sexual health pages
- `03-products`: product detail page screenshots
- `04-selected-states`: selected interaction states such as cart drawer and FAQ open

Captured viewports:

- `desktop-1440` at 1440 x 1000
- `laptop-1280` at 1280 x 900
- `tablet-768` at 768 x 1024
- `mobile-390` at 390 x 844

Notes:

- Total captured screenshots: 75, plus README/summary metadata in the image output folder.
- The captured cart drawer state is a visual reference only for the first pass; the WordPress cart/drawer experience is excluded for now.
- Portal/login and demo screenshots are useful for visual vocabulary only; those areas are out of scope.

## Redesign Asset Folder

The current grunge redesign asset folder contains:

- Logos/icons: `red and white logo.svg`, `arrow.svg`, `cta-arrow.svg`, `box.svg`, `checkbox.svg`, `doctor.svg`, `headphones.svg`, `laptop-check.svg`, `rx.svg`, `vial.svg`
- Backgrounds/textures: `hero bg.png`, `concrete-texture.jpg`, `bg-genetic-wire.webp`, `red-dots-grid-background.webp`, `grunge black section bg blank.png`, `section bg 2.png`, `section bg 4.png`, `section bg 5.png`, `thin section bg.png`, `chemistry background enhancer.png`
- People/product imagery: `mgrx-hero-team.webp`, `mgrx-phone-care-journey.webp`, `hospital-staff.webp`, `guy-helping-1.webp`, `guy-helping-2.webp`, `guy-sad.webp`, `pharma support staff tp bg.png`, `section bg guy w muscles.png`
- Category/product imagery: `weight-loss-category-vials.webp`, `peptides-category-vials.webp`, `sexual-health-products.webp`, `trt-category-image.webp`, `quest-logo-new.webp`, `icon-box.webp`, `muscle-icon.webp`
- Other: `customer case section full width background.png`, `potential letter overlay image full opacity.png`

## Porting Scope

Implement these surfaces in the live WordPress project:

- Home page
- All product detail pages / PDPs for all live products
- All category page URLs that exist as product categories in WordPress admin
- Supplemental footer pages, including at minimum:
  - About
  - Affiliates
  - Contact
  - Privacy Policy
  - Terms of Service

Potentially related, but not yet explicitly in scope:

- Cart
- Checkout
- Thank-you / confirmation
- My account
- Intake flow

## Explicitly Out Of Scope

The Next.js clone includes mock application areas that should not be replicated into WordPress as part of this redesign unless separately requested:

- Admin dashboard mockups
- Patient portal mockups
- Intake app mockups
- Product management mockups
- Demo instruction pages
- Cart drawer / popout cart behavior from the Next.js preview
- Cart page redesign for the first pass
- Checkout redesign for the first pass
- Any fake/local cart state that does not map to WooCommerce behavior

## Content Source Of Truth

- Preview design copy wins over current production copy when they differ.
- For supplemental footer pages, replace Elementor page content with code template overrides.
- Preserve the existing real page subject matter and required legal/business content, but use the new design implementation and preview copy as the preferred wording source.
- No footer pages beyond the ones listed in the updated design are required for the first pass.

## URL Rules

- Keep all current production URLs unchanged.
- Redesign existing WordPress/WooCommerce routes in place.
- Do not introduce new public URL structures unless explicitly requested.
- If a Next.js preview route differs from the live WordPress route, the live WordPress route wins and should receive the matching redesign.

## Live Production URL Inventory

Discovered from the live WordPress REST API and Rank Math sitemap on 2026-08-07.

### Published Products / PDPs

All of these are included in the first pass.

| Product | Slug | ID | URL | Product Category IDs |
|---|---:|---:|---|---|
| HCG | `hcg` | 4779 | `https://myogenixpharma.com/product/hcg/` | 19 |
| Compound Retatrutide | `compound-retatrutide` | 4537 | `https://myogenixpharma.com/product/compound-retatrutide/` | 25 |
| Epithalon 2mg/ml (5ml) | `epithalon` | 4257 | `https://myogenixpharma.com/product/epithalon/` | 31 |
| MOTSc 2mg/ml (5ml) | `motsc` | 4253 | `https://myogenixpharma.com/product/motsc/` | 31 |
| BPC 3mg/ml (5ml) | `bpc` | 4249 | `https://myogenixpharma.com/product/bpc/` | 31 |
| TIRZEPATIDE | `compound-tirzepatide` | 4063 | `https://myogenixpharma.com/product/compound-tirzepatide/` | 25 |
| SEMAGLUTIDE | `compound-semaglutide` | 4041 | `https://myogenixpharma.com/product/compound-semaglutide/` | 25 |
| KlOW (BPC157 3mg/ GHK-CU 10mg/ TB500 3mg/ KPV 3mg, 5ml vial) | `klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg` | 2819 | `https://myogenixpharma.com/product/klow-stack-bpc157-10mg-ghk-cu-50mg-tb50010mg-kpv-10mg/` | 31 |
| TESAMORELIN / IPAMORELIN 2x Blend (3mg + 2mg, 5ml vial) | `tesamorelin-ipamorelin` | 2803 | `https://myogenixpharma.com/product/tesamorelin-ipamorelin/` | 31 |
| CJC 1295 / IPAMORELIN (1.2mg + 2mg, 5ml vial) | `cjc1295-ipamorelin` | 2619 | `https://myogenixpharma.com/product/cjc1295-ipamorelin/` | 31 |
| WOLVERINE BPC157 / TB500 (3mg/3mg, 5ml) | `2606` | 2606 | `https://myogenixpharma.com/product/2606/` | 31 |
| TADALAFIL (generic Cialis) | `compound-oral-tadalafil` | 1886 | `https://myogenixpharma.com/product/compound-oral-tadalafil/` | 26 |
| SILDENAFIL (generic Viagra) | `compound-sildenafil` | 1883 | `https://myogenixpharma.com/product/compound-sildenafil/` | 26 |
| NAD 100mg/ml, 10ml vial (injectable) | `compound-injectable-nad` | 1874 | `https://myogenixpharma.com/product/compound-injectable-nad/` | 31 |
| SERMORELIN 10mg | `compound-injectable-sermorelin` | 1871 | `https://myogenixpharma.com/product/compound-injectable-sermorelin/` | 31 |
| GLUTATHIONE 200mg/ml, 10ml vial | `compound-injectable-glutathione` | 1868 | `https://myogenixpharma.com/product/compound-injectable-glutathione/` | 31 |
| TESTOSTERONE CYPIONATE | `testosterone` | 883 | `https://myogenixpharma.com/product/testosterone/` | 19 |

### Product Category Archives

All category archive URLs return 200 and should keep their current URLs.

| Category | Slug | ID | Count | URL | Existing Theme Template |
|---|---:|---:|---:|---|---|
| Mens Health | `mens-health` | 19 | 2 | `https://myogenixpharma.com/product-category/mens-health/` | `woocommerce/taxonomy-product_cat-mens-health.php` |
| peptides-longevity | `peptides-longevity` | 31 | 10 | `https://myogenixpharma.com/product-category/peptides-longevity/` | `woocommerce/taxonomy-product_cat-peptides-longevity.php` |
| sexual-health | `sexual-health` | 26 | 2 | `https://myogenixpharma.com/product-category/sexual-health/` | `woocommerce/taxonomy-product_cat-sexual-health.php` |
| Uncategorized | `uncategorized` | 18 | 0 | `https://myogenixpharma.com/product-category/uncategorized/` | none currently |
| Weight Loss | `weight-loss` | 25 | 2 | `https://myogenixpharma.com/product-category/weight-loss/` | `woocommerce/taxonomy-product_cat-weight-loss.php` |
| Womens Health | `womens-health` | 23 | 0 | `https://myogenixpharma.com/product-category/womens-health/` | none currently |

### Program / Landing Pages

These are page URLs, not WooCommerce product category archive URLs. They should stay in place if redesigned.

| Page | URL | Status | Current Template |
|---|---|---:|---|
| Home | `https://myogenixpharma.com/` | 200 | `front-page.php` / WP front page |
| Mens Health | `https://myogenixpharma.com/mens-health/` | 200 | Elementor header/footer |
| Weight Management | `https://myogenixpharma.com/weight-management/` | 200 | Elementor header/footer |
| Sexual Health | `https://myogenixpharma.com/sexual-health/` | 200 | Elementor header/footer |
| Wellness | `https://myogenixpharma.com/wellness/` | 200 | Elementor header/footer |
| Womens Health | `https://myogenixpharma.com/womens-health/` | 200 | Elementor header/footer |
| Shop | `https://myogenixpharma.com/shop/` | 200 | Elementor header/footer / Woo shop |

### Supplemental / Footer-Adjacent Pages

| Desired Design Surface | Production URL Found | Status | Notes |
|---|---|---:|---|
| Privacy Policy | `https://myogenixpharma.com/privacy-policy/` | 200 | Existing page, Elementor header/footer template |
| Terms of Service | `https://myogenixpharma.com/terms-of-service/` | 200 | Existing page, Elementor header/footer template |
| Contact | `https://myogenixpharma.com/contact/` | 404 | Preview route exists; production equivalent may be `https://myogenixpharma.com/reach-a-concierge/` |
| Affiliates | `https://myogenixpharma.com/affiliates/` | 404 | Preview route exists; production affiliate pages are `/affiliate-area/`, `/affiliate-login/`, and `/affiliate-registration/` |
| About | `https://myogenixpharma.com/about/` | 404 | Preview route exists; no production page with this URL found |

Additional legal/operations pages exist in production and should not be broken by redesign work:

- `https://myogenixpharma.com/ccpa/`
- `https://myogenixpharma.com/notice-of-privacy-practices/`
- `https://myogenixpharma.com/medication-information/`
- `https://myogenixpharma.com/medical-consent/`
- `https://myogenixpharma.com/ada-compliance/`
- `https://myogenixpharma.com/bill-of-rights/`

### Explicitly Excluded Existing Commerce URLs

These must continue working but are not first-pass redesign targets:

- `https://myogenixpharma.com/cart/`
- `https://myogenixpharma.com/cart-1/`
- `https://myogenixpharma.com/checkout/`
- `https://myogenixpharma.com/checkout-1/`
- `https://myogenixpharma.com/my-account/`

## Route Mapping Watchlist

- Decide whether the preview `Contact` design maps to `/reach-a-concierge/`, or whether a new `/contact/` page should be created later.
- Decide whether the preview `Affiliates` design maps to `/affiliate-area/`, `/affiliate-registration/`, a new `/affiliates/` page, or a combination.
- Decide whether the preview `About` design should create a new `/about/` page later; no production URL currently exists.
- Map preview `/peptides` to the live route before implementation. The current production program-style page appears to be `/wellness/`, while the WooCommerce archive is `/product-category/peptides-longevity/`.

## Relevant Next.js Source Files

Primary app routes to review:

- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/layout.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/cart/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/about/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/affiliates/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/contact/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/privacy-policy/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/terms-of-service/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/weight-management/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/weight-management/tirzepatide/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/weight-management/semaglutide/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/weight-management/retatrutide/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/peptides/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/peptides/[medicine]/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/sexual-health/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/sexual-health/[medicine]/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/mens-health/page.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/(site)/mens-health/[medicine]/page.tsx`

Primary reusable components to review:

- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/layout/Navbar.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/layout/Footer.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/home/Hero.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/home/ProgramsGrid.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/home/TrustStrip.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/home/NewPeptides.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/category/CategoryLanding.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/ProductHero.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/Configurator.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/PeptideConfigurator.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/OrderSummary.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/DosePicker.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/MonthSelector.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/PurchaseTypeToggle.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/cart/CartItemCard.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/cart/CartDrawer.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/sections/HowItWorks.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/sections/ClinicalFaq.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/supplemental/SupplementalPage.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/grunge/SupportOptionsCard.tsx`

## Design System Porting Notes

Source files:

- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/globals.css`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/app/layout.tsx`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/lib/ui-font.ts`
- `/Users/lukeflaherty/dev/client/myogenixpharma/src/components/pdp/PdpDesignSystem.tsx`

Fonts used in the preview:

- Body font: Poppins, weights 400, 500, 600, 700, 800.
- UI/display font: Bebas Neue, weight 400.
- Grunge headline texture helper: Oswald, weight 700, used with `.grunge-word`.

Core visual vocabulary:

- Dominant background: black / near-black.
- Primary accent: red, commonly Tailwind `red-600` / hex `#dc2626`.
- Supporting text: zinc/neutral grays.
- Section treatment: black grunge backgrounds, red borders, radial red glows, concrete texture text fills, thin red separators.
- Common shared utility classes to port: `.grunge-word`, `.grunge-panel`, `.grunge-kicker`.

Implementation notes:

- Enqueue Google Fonts from WordPress rather than relying on Next font output.
- Port Tailwind utility output into hand-authored scoped CSS; do not add a Tailwind build step to the WordPress theme unless explicitly approved.
- Use `assets/images/grunge-redesign/` as the theme asset destination.
- Preserve filenames where practical, including spaces, unless URL handling becomes painful. Existing theme code already has a helper pattern for rawurlencoding image path segments.
- Do not port `CartProvider`, `CartDrawer`, `DemoBanner`, admin, portal, or intake wrappers into WordPress for the first pass.
- Header/footer links need production URL remapping before being copied from the preview.

## Preview To Production Route Mapping Draft

| Preview Route | Production Route Candidate | Status |
|---|---|---|
| `/` | `/` | Direct match |
| `/weight-management` | `/weight-management/` and/or `/product-category/weight-loss/` | Needs implementation mapping; both production URLs exist |
| `/mens-health` | `/mens-health/` and/or `/product-category/mens-health/` | Needs implementation mapping; both production URLs exist |
| `/sexual-health` | `/sexual-health/` and/or `/product-category/sexual-health/` | Needs implementation mapping; both production URLs exist |
| `/peptides` | `/wellness/` and/or `/product-category/peptides-longevity/` | Needs implementation mapping; production has no `/peptides/` page |
| `/about` | none found | Production currently 404 |
| `/contact` | `/reach-a-concierge/` candidate | Production `/contact/` currently 404 |
| `/affiliates` | `/affiliate-area/`, `/affiliate-registration/`, or new page candidate | Production `/affiliates/` currently 404 |
| `/privacy-policy` | `/privacy-policy/` | Direct match |
| `/terms-of-service` | `/terms-of-service/` | Direct match |

## WordPress Implementation Targets

Theme files likely involved:

- `front-page.php`
- `header.php`
- `footer.php`
- `functions.php`
- `template-parts/site-header.php`
- `template-parts/site-footer.php`
- `woocommerce/content-single-product.php`
- `woocommerce/single-product.php`
- `woocommerce/taxonomy-product_cat-weight-loss.php`
- `woocommerce/taxonomy-product_cat-peptides-longevity.php`
- `woocommerce/taxonomy-product_cat-sexual-health.php`
- `woocommerce/taxonomy-product_cat-mens-health.php`

Expected new/expanded assets:

- `assets/images/grunge-redesign/`
- `assets/css/grunge-redesign.css`, or split by surface if safer
- `assets/js/grunge-redesign.js`, or split by surface if safer

## Production Workflow

1. Work in `/Users/lukeflaherty/dev/client/myogenixpharma-wp/wp-content/themes/myogenix-theme`.
2. Keep changes scoped to the child theme.
3. Copy assets from the Next.js public folder into the theme, preserving file names where practical.
4. Port one surface at a time from React/TypeScript/Tailwind into WordPress PHP/CSS/JS.
5. Preserve WooCommerce hooks and live ordering behavior.
6. Run available local checks before deploy.
7. Commit to `main`.
8. Push `main`.
9. Wait for WordPress.com deployment.
10. QA on the live production URL.
11. Make follow-up commits for visual or functional issues found in live QA.

## Suggested Rollout Order

1. Shared asset import and global redesign tokens/classes.
2. Header/footer shell.
3. Home page.
4. Supplemental footer pages.
5. Category pages for all live WooCommerce product categories.
6. PDPs for all live products.
7. Cart/checkout/thank-you only if later brought into scope.

Cart and checkout-adjacent work should happen later because production ordering behavior depends on WooCommerce, WooCommerce Subscriptions, Stripe, Prescribery, AffiliateWP, and active Code Snippets.

## QA Expectations

Use live production QA after each deploy:

- Compare against `https://myogenix-pharma.vercel.app/`.
- Check desktop, tablet, and mobile responsive states.
- Verify navigation links and CTAs.
- Verify images load from the WordPress theme.
- Verify no Elementor/header/footer conflicts on replaced surfaces.
- For category/PDP work, verify WooCommerce data is dynamic and add-to-cart behavior still works.
- Do not introduce the Next.js popout cart in the first pass.
- For any future ordering surfaces, preserve affiliate tracking, subscription logic, prescription approval messaging, Stripe behavior, and Prescribery hooks.

## Open Questions / Needed Inputs

- Any must-not-change URLs, pages, snippets, or WooCommerce flows.
- Whether to commit this workflow document before implementation begins.
