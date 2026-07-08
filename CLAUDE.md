# Myogenix Pharma — Theme Development Context

This file is the source of truth for Claude Code working in this repository. Read it fully before making any changes.

---

## What This Repo Is

This is the custom child theme for **myogenixpharma.com**, a WooCommerce store running on WordPress.com Business plan. The theme is named `myogenix-theme` and is a child of Hello Elementor.

The goal of this codebase is to own the WooCommerce template layer in code — PDPs, cart, checkout, category/shop archives, and related commerce pages — while leaving Elementor in control of copy/marketing pages (About, blog, landing pages). WordPress admin remains the system of record for products, orders, affiliates, discounts, and subscriptions.

**You are only responsible for files in this repo.** Do not attempt to edit WordPress core, plugins, or Elementor page content. Those are managed elsewhere.

---

## CRITICAL: Elementor Pro Theme Builder Currently Controls Commerce Pages

The current PDPs, cart, checkout, and some archives are rendered by **Elementor Pro's Theme Builder**, not by WooCommerce's default template system. This means:

- Elementor Pro has a **Single Product** template (ID #990) that overrides WooCommerce's `single-product.php` for all products
- Elementor Pro intercepts the template loader at a higher priority than theme overrides
- Creating `woocommerce/single-product.php` in this theme **will not take effect** on products assigned to Elementor's Single Product template

**To migrate a commerce page from Elementor to code control, the process is:**

1. In WP Admin → Templates → Theme Builder, find the Elementor template controlling the page (Single Product, Cart, Checkout, Product Archive, etc.)
2. Either:
   - **Change display conditions** so it applies to fewer products (e.g. only products in a specific category), freeing up the rest to use the theme's template override, OR
   - **Set to draft / disable** the Elementor template entirely so all products fall through to the theme's template
3. Then create the corresponding `woocommerce/*.php` file in this theme
4. Deploy to staging, verify the new template renders, then promote to production

**Known Elementor Theme Builder templates in use (as of handoff):**
- Template #898 — Header
- Template #914 — Footer
- Template #990 — Single Product (controls all PDPs)

Always check the WP Admin → Templates → Theme Builder before assuming a WooCommerce template override will render. If the page still looks Elementor-built after deploy, an Elementor template is still intercepting.

---

## Repository

- **GitHub:** `https://github.com/LukeFlaherty/myogenixpharma-wp`
- **Local path:** `~/dev/client/myogenixpharma-wp/wp-content/themes/myogenix-theme`
- **Structure:** This repo represents only the theme folder. It deploys directly to `/wp-content/themes/myogenix-theme/` on the server.

---

## Local Plugin Reference (Read-Only)

A read-only mirror of the key plugins and parent theme is stored locally at:

```
~/dev/client/myogenixpharma-wp/_reference/
├── woocommerce/                     ← WooCommerce core templates and hooks
├── woocommerce-subscriptions/       ← Subscription product types, cart, checkout logic
├── elementor-pro/                   ← Theme Builder template loader, WooCommerce widgets
├── checkout-upsell-and-order-bumps/ ← UpsellWP hook injection points
├── affiliate-wp/                    ← AffiliateWP tracking JS and hooks
├── prescribery-wc-integration/      ← Prescription approval flow (small plugin, read fully)
├── woo-stripe-payment/              ← Stripe gateway checkout integration
└── hello-elementor/                 ← Parent theme template structure
```

**This folder is NOT in git and is never committed.** It is a local reference only — do not modify any files here.

**When to use it:**
- Before overriding a WooCommerce template, read the default from `_reference/woocommerce/templates/` rather than fetching from GitHub
- When a plugin injects unexpected output, grep `_reference/{plugin}/` for the hook name to find where it's coming from
- When building checkout or cart templates, read `_reference/woocommerce-subscriptions/templates/` for subscription-specific template partials that need to be included
- When debugging Elementor template interception, check `_reference/elementor-pro/modules/woocommerce/` for how it hooks into the template loader
- For Prescribery specifically — it's a small plugin (read `_reference/prescribery-wc-integration/prescribery-wc-integration.php` fully) — understand its hooks before touching checkout or order flow

**Keeping it current:** Re-sync a plugin after it updates on the server:
```bash
rsync -avz --progress \
  --exclude='languages/' --exclude='i18n/' --exclude='node_modules/' --exclude='vendor/' --exclude='build/' --exclude='assets/' \
  --exclude='*.map' --exclude='*.min.js' --exclude='*.min.css' --exclude='*.po' --exclude='*.mo' --exclude='*.json' \
  ecom-adamb01445d7f0c-kpnrf.wordpress.com@sftp.wp.com:/srv/htdocs/wp-content/plugins/{plugin-folder}/ \
  ~/dev/client/myogenixpharma-wp/_reference/{plugin-folder}/
```

---

## Environments

| Environment | URL | Branch | Purpose |
|---|---|---|---|
| Production | `https://myogenixpharma.com` | `main` | Live site — only environment in use |

**Deploy directly to production.** Push to `main` and verify on the live site.

## Deploy Pipeline

Deploys are automatic via WordPress.com GitHub Deployments (native, no GitHub Actions required).

- Push to `main` branch → deploys to production in ~30 seconds

**Staging is not used.** Deploy directly to production on the `main` branch.

### Standard workflow

```bash
git checkout main

# Make changes, commit
git add <files>
git commit -m "describe what you changed"
git push origin main

# Site is live in ~30 seconds
# Verify on https://myogenixpharma.com
```

**Rules:**
- Always work on and push to `main`
- Pull before pushing if working across machines: `git pull origin main`

---

## Server Access

- **Host:** WordPress.com Business plan
- **SFTP:** `sftp.wp.com` port 22
- **Production SSH:** `ssh ecom-adamb01445d7f0c-kpnrf.wordpress.com@ssh.wp.com`
- **Staging SSH:** `ssh staging-b59c-ecom-adamb01445d7f0c-kpnrf.wordpress.com@ssh.wp.com`
- **Auth:** SSH key (Luke's MacBook key attached — passwordless)
- **Theme path on server:** `/srv/htdocs/wp-content/themes/myogenix-theme/`
- **WP Admin:** `https://myogenixpharma.com/wp-admin`

**Always verify which environment you're in before running WP-CLI:**
```bash
wp option get siteurl
# Must return https://myogenixpharma.com for production
# Must return https://staging-b59c-... for staging
```

SSH gives WP-CLI access for quick debugging. Useful commands:
```bash
wp theme list
wp theme activate myogenix-theme          # use after any Jetpack restore
wp plugin list --status=active
wp option get template                    # confirms active theme
wp option get stylesheet                  # confirms active child theme
wp post list --post_type=product --fields=ID,post_title,post_status
wp cache flush
wp elementor flush-css                    # must run after theme activation or condition changes
```

Credentials (SFTP password) are stored securely by Luke. Do not store credentials in this repo.

---

## Theme Structure

```
myogenix-theme/
├── CLAUDE.md                  ← you are here
├── style.css                  ← theme declaration (Template: hello-elementor)
├── functions.php              ← enqueue parent styles, custom hooks, functions
├── woocommerce/               ← WooCommerce template overrides (primary work area)
│   ├── single-product.php     ← PDP
│   ├── content-single-product.php
│   ├── archive-product.php    ← shop + category pages
│   ├── content-product.php    ← product tile in archives
│   ├── cart/
│   │   ├── cart.php
│   │   ├── cart-empty.php
│   │   └── cart-totals.php
│   ├── checkout/
│   │   ├── form-checkout.php
│   │   ├── form-pay.php
│   │   └── thankyou.php
│   ├── myaccount/
│   │   └── my-account.php
│   └── global/
│       └── quantity-input.php
└── assets/                    ← custom CSS, JS (add as needed)
    ├── css/
    └── js/
```

### How WooCommerce template overrides work

WooCommerce looks for templates in this order:
1. `your-theme/woocommerce/{template-path}.php`
2. `woocommerce/templates/{template-path}.php` (default)

To override a template, **copy the WooCommerce default from the plugin**, then customize. Defaults live on the server at `/srv/htdocs/wp-content/plugins/woocommerce/templates/`. You can pull one down via SFTP or SSH if you need a starting reference.

Do NOT write WooCommerce templates from scratch without copying the default — you'll miss critical hooks that other plugins depend on.

### Preserving hooks is mandatory

When you override a WooCommerce template, keep every `do_action()` and `apply_filters()` call intact. Example from `single-product.php`:

```php
<?php do_action( 'woocommerce_before_single_product' ); ?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>
    <?php do_action( 'woocommerce_before_single_product_summary' ); ?>
    
    <div class="summary entry-summary">
        <?php do_action( 'woocommerce_single_product_summary' ); ?>
    </div>

    <?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>
```

`woocommerce_single_product_summary` is a big one — it fires the title, price, excerpt, add-to-cart form, variations form, and meta. If you remove it, variations break, add-to-cart breaks, subscriptions break. Don't remove it unless you're manually replicating every piece.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Hosting | WordPress.com Business |
| CMS | WordPress 6.9.4 |
| Ecommerce | WooCommerce 10.7.0 |
| Subscriptions | WooCommerce Subscriptions 8.4.0 |
| Page builder | Elementor Pro 4.0.2 (marketing + currently commerce pages) |
| Parent theme | Hello Elementor 3.4.6 |
| Active child theme | Myogenix Theme (this repo) |
| PHP | 8.3.30 |
| Web server | nginx |
| Database | MariaDB 11.4.7 |

---

## Active Plugins (relevant to template work)

These plugins inject functionality via WooCommerce hooks. When building templates, ensure their hooks fire correctly:

- **WooCommerce Subscriptions** — many products are variable subscriptions (e.g. 1-month, 3-month plans). Affects price display, add-to-cart flow, and checkout totals.
- **UpsellWP (Checkout Upsell and Order Bumps)** — injects upsell offers into cart and checkout. Listens to `woocommerce_before_cart`, `woocommerce_review_order_before_payment`, and similar hooks.
- **AffiliateWP + Lifetime Commissions** — affiliate tracking via `affwp_ref` cookie. Referral data is captured when `?ref=XXX` URLs hit the site. Must not break tracking script (`affwp-tracking-js`).
- **Prescribery WC Integration** — custom prescription approval flow. Products requiring prescriptions have a multi-step approval gate before charge. Injects logic into order status transitions and displays "Expected Charge After Approval" on cart/checkout/thank-you pages.
- **Prescription – Auto Charge Approved Orders** — companion plugin that triggers charges when Prescribery approves a prescription.
- **Payment Plugins for Stripe WooCommerce** — Stripe payment gateway in checkout.
- **Advanced Order Export For WooCommerce** — admin-only, doesn't affect front-end templates.
- **Code Snippets** — 16 active snippets handling various overrides (see below).
- **Rank Math SEO** — injects meta tags via wp_head. Preserve `<?php wp_head(); ?>` in any custom layout.
- **Autoptimize** — CSS/JS minification and caching. May need a cache purge after CSS changes: `wp autoptimize clear` via SSH, or from the admin bar Autoptimize menu.

---

## Active Code Snippets (Reference Only — Do NOT Duplicate Logic)

These snippets run globally via the Code Snippets plugin and may interact with templates. Before overriding a piece of functionality, check the matching snippet in WP Admin → Snippets:

| Snippet | What it does | Impact on templates |
|---|---|---|
| Login/Register Toggle | Custom auth UI behavior on my-account page | `myaccount/form-login.php` |
| Subscription Monthly Price Display | Modifies how subscription prices render | price display on PDP, cart, checkout |
| Force Redirect | Redirects certain URLs | routing — check if changing permalinks |
| 3 Months text show in cart page | Injects "$X x 3 Months" breakdown on 3-month plan cart items | `cart/cart.php` display |
| affiliate tracking | Custom AffiliateWP tracking enhancements | checkout order meta |
| category_url_filter | URL structure for product categories | archive pages, category permalinks |
| Force Gateway | Forces specific payment gateway based on conditions | checkout payment methods |
| Dynamic Price Update variations | JS price updates on variation select | PDP variations form |
| Force offer cart hide | Hides certain UpsellWP offers in cart | cart page |
| Expected Product Charge After Approval | Shows future charge for prescription products | cart, checkout, thank-you |
| Auto Recalculate Affiliate Referral When Order Updates | Referral recalc on order status change | admin/background only |
| displaying state selector filter | State dropdown behavior in checkout | `checkout/form-billing.php` |
| bottle ui variable | Custom "bottle count" UI on PDP (variable product display) | PDP variations form — the custom radio box UI seen in source |
| show_tracking_in_my_account | Shipment tracking display in account area | `myaccount/orders.php`, `myaccount/view-order.php` |
| subscription_template override | Subscription-specific template customization | `single-product/add-to-cart/variable-subscription.php` likely |
| thankyoupage-admin-expected_charge | Thank you page charge display for admin users | `checkout/thankyou.php` |

**If your template work overlaps with any of these snippets, either:**
- Leave the snippet in place and work around it, OR
- Move the snippet's logic into the theme's `functions.php` and deactivate the snippet (cleaner long-term, but requires testing)

Don't duplicate snippet logic inside a template — you'll get double execution and bugs.

---

## What Has Been Built

### Weight Management PDP (live on staging, 2026-05)

`woocommerce/single-product.php` and `woocommerce/content-single-product.php` are live and handling both weight management products (`compound-tirzepatide`, `compound-semaglutide`). All other products fall through to default WooCommerce rendering.

**How the configurator works:**
- PHP builds `$price_matrix` and `$variation_map` by looping published WC variations
- Both are JSON-encoded into `data-*` attributes on `#pdp-cfg`
- `assets/js/pdp.js` reads those attributes and drives all UI — supply length buttons, per-month dose selectors, order summary, and add-to-cart

**Dose dropdowns are dynamic — do not hardcode them.** Doses are derived from the product's dose attribute terms (ordered per WP Admin), filtered to those with at least one published variation. Adding or retiring a dose in WP Admin automatically updates the dropdown.

**WooCommerce attribute names differ between production and staging environments:**

| Attribute | Production slugs | Staging slugs |
|---|---|---|
| Dose | `pa_individual-dose` (`10-mg`, `15-mg`, `20-mg` …) | `pa_dosage` (`10mg`, `20mg`, `30mg` …) |
| Bottle/vial count | `pa_vial` (`1-vial`, `2-vial`, `3-vial`) — both products | Tirzepatide: `pa_wm-bottle`, Semaglutide: `pa_vial` |
| Subscription plan | None (plan determined by vial count) | `pa_wm-subscription-plan` (`1-month`, `3-month`) |

The PHP template auto-detects which attribute is present (`pa_individual-dose` takes precedence). The template normalizes all bottle/vial slugs to `1-bottle`/`2-bottle`/`3-bottle` internally so JS stays consistent across environments.

**Dose display names:** Dose term slugs (e.g. `10-mg`) must be converted to term names (e.g. `10 mg`) before showing to users. Use `myogenix_dose_display( $slug )` in `functions.php` — it looks up the term name from either taxonomy and falls back to the raw slug. Apply this function anywhere a dose value is rendered (cart title, meta boxes, order Rx Summary).

**Dose escalation (multi-month supply):** Customers pick a separate dose per month for 2- and 3-month supplies. `dose_month_1/2/3` are passed as URL params on add-to-cart, captured in cart item data via `woocommerce_add_cart_item_data`, displayed in cart/checkout via `woocommerce_get_item_data`, and saved to the order line item via `woocommerce_checkout_create_order_line_item`.

### Rx Summary order meta

Every weight management order line item gets an `Rx Summary` meta field written at checkout by the hook in `functions.php`. This same string is also set as the WC order item name via `$item->set_name()` — it is what Prescribery reads from `line_items[].name` in the WC REST API for "reason for visit."

**Format:** `{Drug} - {dose1}mg({weekly1}mg/wk), {dose2}mg({weekly2}mg/wk), ...`

| Scenario | Example |
|---|---|
| 1-month, single dose | `Tirzepatide - 10mg(2.5mg/wk)` |
| 3-month, same dose | `Tirzepatide - 10mg(2.5mg/wk)` |
| 2-month, escalating doses | `Tirzepatide - 10mg(2.5mg/wk), 20mg(5mg/wk)` |
| 3-month, escalating doses | `Tirzepatide - 10mg(2.5mg/wk), 20mg(5mg/wk), 30mg(7.5mg/wk)` |

Rules:
- Drug name = title case of product slug minus `compound-` prefix (e.g. "Tirzepatide", "Semaglutide")
- Each dose includes its weekly rate in parentheses: `{mg}mg({mg/4}mg/wk)`, trailing zeros stripped
- Doses separated by `, `; no QTY suffix — Prescribery manages quantity internally
- If all months share the same dose, show it once; if any differ, list all months in order
- Dose source priority: `dose_month_*` URL params first; if absent, falls back to `attribute_pa_individual-dose` / `attribute_pa_dosage` on the variation. This handles both dose-escalation BYO orders and standard single-variation add-to-cart.
- The individual "Month 1/2/3 Dose" meta fields are also saved alongside Rx Summary — don't remove them
- Prescribery flow: order placed → `set_name()` writes to `order_item_name` DB → order goes to processing → Prescribery callback fires (sends only order ID) → Prescribery fetches full order via WC REST API → reads `line_items[].name`

---

## Current PDP Structure (for reference when replacing)

The existing Elementor-built PDP (template #990) includes:

- Product image (left)
- Product title + description (right)
- Price display ("From: $250.00 / month")
- **Custom bottle UI** — radio box variant selector styled as cards (dosage, vial count, subscription plan). Rendered by snippet "bottle ui variable" via JS. See `.custom-radio-box-container` and `.variation-box-card` in existing HTML.
- Quantity + Add to cart button
- Below-fold "Personalized Healthcare in 4 Simple Steps" section (Elementor-built, keep in Elementor)
- FAQ accordion (Elementor-built, keep in Elementor)
- "Explore More Treatment Lines" category links (Elementor-built, keep in Elementor)

**Variable product attributes in use:**
- `pa_dosage` — 1mg, 2mg, 4mg, 6mg, 10mg
- `pa_vial` — 1-vial, 2-vial, 3-vial
- `pa_wm-subscription-plan` — 1-month, 3-month

**When building the new PDP template:**
- Replicate the bottle UI variant selector (or invoke the existing snippet's JS)
- Preserve `variations_form` structure exactly — it drives the variation_data JSON that all the custom JS keys off
- Keep the existing body class structure so plugin CSS/JS continues to target correctly
- The below-fold marketing content can stay Elementor-controlled by using a `do_action()` hook or by having the PDP template render only the product summary and letting Elementor hooks fire after

---

## What To Build Next (Priority Order)

1. **Product archive (`woocommerce/archive-product.php`)** — category/shop pages
2. **Cart (`woocommerce/cart/cart.php`)** — preserve UpsellWP hooks, 3-month breakdown snippet behavior
3. **Checkout (`woocommerce/checkout/form-checkout.php`)** — preserve Stripe, subscription totals, state selector, prescription approval flow
4. **Thank you (`woocommerce/checkout/thankyou.php`)** — preserve expected charge display

---

## Key WooCommerce Hook Reference

When building templates, these are the critical hooks to preserve:

### PDP
```php
do_action( 'woocommerce_before_single_product' );
do_action( 'woocommerce_before_single_product_summary' );
do_action( 'woocommerce_single_product_summary' );
    // fires in order: title (5), rating (10), price (10), excerpt (20), add_to_cart (30), meta (40), sharing (50)
do_action( 'woocommerce_after_single_product_summary' );
    // tabs (10), upsells (15), related (20)
do_action( 'woocommerce_after_single_product' );
```

### Cart
```php
do_action( 'woocommerce_before_cart' );
do_action( 'woocommerce_before_cart_table' );
do_action( 'woocommerce_before_cart_contents' );
do_action( 'woocommerce_cart_contents' );
do_action( 'woocommerce_after_cart_contents' );
do_action( 'woocommerce_cart_coupon' );
do_action( 'woocommerce_cart_actions' );
do_action( 'woocommerce_after_cart_table' );
do_action( 'woocommerce_cart_collaterals' );  // cross-sells, cart totals
do_action( 'woocommerce_after_cart' );
```

### Checkout
```php
do_action( 'woocommerce_before_checkout_form' );
do_action( 'woocommerce_checkout_before_customer_details' );
do_action( 'woocommerce_checkout_billing' );
do_action( 'woocommerce_checkout_shipping' );
do_action( 'woocommerce_checkout_after_customer_details' );
do_action( 'woocommerce_checkout_before_order_review_heading' );
do_action( 'woocommerce_checkout_before_order_review' );
do_action( 'woocommerce_checkout_order_review' );  // table, totals, payment, submit
do_action( 'woocommerce_checkout_after_order_review' );
do_action( 'woocommerce_after_checkout_form' );
```

### Archive / Shop
```php
do_action( 'woocommerce_before_main_content' );
do_action( 'woocommerce_archive_description' );
do_action( 'woocommerce_before_shop_loop' );
woocommerce_product_loop_start();
// product loop
woocommerce_product_loop_end();
do_action( 'woocommerce_after_shop_loop' );
do_action( 'woocommerce_after_main_content' );
```

---

## Elementor Conditions Management

Elementor stores template display conditions in TWO places. Both must be in sync or templates won't render correctly:

| Store | What it is | How to read | How to update |
|---|---|---|---|
| `_elementor_conditions` post meta on the template post | Source of truth — what the UI edits | `wp post meta get 990 _elementor_conditions` | `wp post meta update 990 _elementor_conditions '[...]' --format=json` |
| `elementor_pro_theme_builder_conditions` wp option | Compiled cache for fast lookup at render time | `wp option get elementor_pro_theme_builder_conditions --format=json` | `wp option update elementor_pro_theme_builder_conditions '{...}' --format=json` |

**If you update only the post meta, the conditions cache still serves the old value — templates appear unchanged.** Always update both, then flush caches.

### Current Single Product conditions (template #990)

```json
["include/product", "exclude/singular/product/4063", "exclude/singular/product/4041", "exclude/singular/product/4537"]
```

- `include/product` — applies to all WooCommerce products
- `exclude/singular/product/4063` — except TIRZEPATIDE (lets our theme override render)
- `exclude/singular/product/4041` — except SEMAGLUTIDE (lets our theme override render)
- `exclude/singular/product/4537` — except RETATRUTIDE (lets page-retatrutide.php render)

**Format note:** Elementor 4.1 changed condition parsing to a 4-part `type/name/sub_name/sub_id` format. The old 3-part `exclude/product/4063` format silently broke after the 4.0→4.1 update — the exclude was skipped because `get_condition('4063')` returns null. Always use the full 4-part `exclude/singular/product/{id}` format.

**These excludes must also be applied on production** after the first code deploy. They are not in git — they live in the database. Use the SSH command under "after any production → staging database sync" above, pointed at the production SSH endpoint.

### Known Elementor Theme Builder templates

| ID | Type | Condition | Notes |
|---|---|---|---|
| #898 | Header | include/general | Entire site |
| #914 | Footer | include/general | Entire site |
| #990 | Single Product | include/product, exclude/4063, exclude/4041 | All products except weight management |
| #2039 | Products Archive | include/product_archive/shop_page | Shop/archive pages |

---

## Rules for This Codebase

- **Never edit `hello-elementor` directly.** All changes go in `myogenix-theme` (the child).
- **Never commit directly to `main`.** Always work on `staging`, verify, then merge.
- **Always copy WooCommerce templates from the plugin, don't write from scratch.** Source location on server: `/srv/htdocs/wp-content/plugins/woocommerce/templates/`.
- **Preserve all `do_action()` and `apply_filters()` calls** in overridden templates. Plugins depend on them.
- **Check Elementor Theme Builder templates before assuming a theme override will render.** Elementor Pro intercepts at a higher priority.
- **Check Code Snippets before touching overlapping functionality.** Several snippets override template behavior.
- **No build tools required.** Plain PHP, CSS, and JS only. No webpack, no npm, no compilation step.
- **Scope all custom CSS** to specific classes or body classes (e.g. `.single-product .my-custom-block`) to avoid conflicts with Elementor pages.
- **Never hardcode product IDs, prices, or user data.** Use WooCommerce API (`wc_get_product()`, `$product->get_price()`, etc.) and WordPress functions (`get_current_user_id()`, etc.).
- **Purge Autoptimize cache** after significant CSS changes via WP Admin toolbar → Autoptimize → Clear CSS/JS Cache, or via SSH: `wp cache flush` and `wp autoptimize clear` if WP-CLI command is available.
- **Test on staging with real affiliate referral URL** (`?ref=test123`) to confirm tracking still works after template changes.

---

## Common Tasks — Quick Reference

### Override a WooCommerce template
1. Find the default at `/srv/htdocs/wp-content/plugins/woocommerce/templates/{path}.php` (pull via SFTP or copy via SSH)
2. Copy to `woocommerce/{path}.php` in this theme
3. Edit, preserve all hooks
4. Commit to `staging`, push, verify on staging URL
5. Merge to `main`

### Add custom CSS/JS
1. Create file in `assets/css/` or `assets/js/`
2. Enqueue in `functions.php`:
   ```php
   add_action( 'wp_enqueue_scripts', function() {
       wp_enqueue_style( 'myogenix-custom', get_stylesheet_directory_uri() . '/assets/css/custom.css', [], '1.0.0' );
       wp_enqueue_script( 'myogenix-custom', get_stylesheet_directory_uri() . '/assets/js/custom.js', ['jquery'], '1.0.0', true );
   });
   ```
3. Commit to `staging`, verify, merge to `main`

### Disable an Elementor Theme Builder template
1. WP Admin → Templates → Theme Builder
2. Find the template (e.g. Single Product #990)
3. Either change its display conditions to a narrower scope, or move it to draft
4. The next page load will fall through to the theme's WooCommerce template override

### Debug a template not rendering
1. Check WP Admin → Templates → Theme Builder for an overriding Elementor template
2. Check WP Admin → Snippets for a hook-based override
3. SSH in and run `wp option get template` and `wp option get stylesheet` to confirm theme is active
4. Check server error logs in WordPress.com dashboard → Logs

---

## Contact / Ownership

- **Developer:** Luke Flaherty, Wave Consulting
- **Client:** Myogenix Pharma
- **WP Admin access:** `https://myogenixpharma.com/wp-admin` (Luke has admin access as `lukefromwave`)
- **WordPress.com dashboard:** `https://wordpress.com/home` → Myogenix Pharma site