# AGAS Design Studio - Milestone 1 (prototype)

> This is an incremental development build for the 3-day AGAS assessment, **not the final submission**. Install it in the isolated local WordPress + WooCommerce environment only. The template gallery, editor, server-side pricing and a cart/order integration are ready for local verification; printing and several specified editor features remain incomplete.

## Tech

- WordPress 7.x, PHP 8.3, WooCommerce.
- **Custom** WordPress plugin (no commercial design-editor plugin).
- **Fabric.js 5.3.1**, loaded from a pinned CDN. Canvas objects are persisted as Fabric JSON.
- Vanilla JavaScript + CSS. This is intentionally lightweight for the three-day timebox; the assessment expressly permits vanilla JavaScript, React, or Vue. React is not required.
- PHP WP REST API for design save and pricing quotes.
- WooCommerce cart item pricing and HPOS-compatible order line-item metadata hooks.

## Install on the user's Mac

The repo folder created earlier is:

`$HOME/AGAS/agas-woocommerce-assessment`

The WordPress container mounts `./plugin/agas-design-studio` into `/var/www/html/wp-content/plugins/agas-design-studio`.

1. Download `AGAS_Design_Studio_Milestone1.zip` and place it in `~/Downloads`.
2. Back up your plugin directory, then extract the zip into `plugin/`:

```bash
cd "$HOME/AGAS/agas-woocommerce-assessment"
cp -R plugin/agas-design-studio "plugin/agas-design-studio.backup.$(date +%Y%m%d%H%M%S)"
unzip -oq "$HOME/Downloads/AGAS_Design_Studio_Milestone1.zip" -d plugin

docker compose exec -T wordpress php -l /var/www/html/wp-content/plugins/agas-design-studio/agas-design-studio.php
```

3. In WordPress admin, go to Plugins > Installed Plugins > AGAS Design Studio and **Activate**. If it was already activated, deactivate and reactivate once to create the pages and seed templates.
4. Visit Pages > Design Templates; open its **View** link. If the exact three products (`Outdoor Vinyl Banner`, `Custom Flag`, `Yard Sign`) exist and are published Simple products, there should be 18 templates across the three categories.
5. From the Gallery click Customize; test editing text and graphics, Save, Continue to Cart, and place a Cash-on-Delivery test order.

**If a gallery is empty:** Verify the product titles exactly match the three required names. Deactivate/reactivate the custom plugin once *after* the products are published. The seeding operation is idempotent and won't duplicate existing template posts of the same slug.

## New pages and admin menu

- `Design Templates` page with shortcode `[agas_template_gallery]`.
- `Online Design Studio` page with shortcode `[agas_designer]`.
- `Design Templates` WordPress admin menu (custom post type). Publish draft templates in the same editor, choose product checkboxes, then click `Edit Template in Design Studio`.
- `Design Templates > Pricing Rules` for price-per-square-foot, minimum, second side and extras.

The WordPress installation may use plain or pretty permalinks. Links are generated with `get_permalink` plus plugin-prefixed query parameters and do not require a particular permalink format.

## Current functionality

- 3 product-aware gallery views; search by title and filter by category.
- 6 seeded templates for each of 3 products, with category tags and styled thumbnail placeholders. An actual thumbnail PNG is stored when an admin edits and saves a template.
- Start Blank and template-to-editor navigation.
- Editable Fabric.js objects including text, rectangles, circles, stars, uploaded JPG/PNG images, background colors, selected object deletion, layer changes, typeface/color/size, undo/redo up to 25 snapshots and full design preview.
- Front/back design states, preset/custom sizes and ft/in/cm display/quotation, optional grommets/pole pocket, responsive simplified page layout.
- Pricing: `max(minimum, area in sq ft * per_sqft) + second_side + extras`. Validated and recomputed on the PHP server on Add to Cart and before cart totals. A browser-supplied `price` is never used.
- Save front/back Fabric JSON + a preview PNG to the WordPress database/uploads; guest editing token link and logged-in design listing at My Account page (basic section).
- Add customized product to WooCommerce cart; attach price, preview and options. Preserve design ID/options in order line metadata and link admins to a read-only editor view.
- Admin can modify pricing settings, publish templates, edit same designer and duplicate a template in the list.

## Data schema

Templates use `agas_template` CPT with tax `agas_template_category` and metadata:
- `_agas_products` array of WooCommerce product IDs.
- `_agas_canvas` Fabric Canvas JSON string, e.g. `{"version":"5.3.1","background":"#173A64","objects":[{"type":"textbox","text":"...","left":90,"top":90}]}`.
- `_agas_thumb_url` URL of a preview PNG after saving in editor.

Customer designs use a private `agas_design` CPT with:
- `_agas_product_id`, `_agas_template_id`, `_agas_options` (dimensions, units, sides, extras), `_agas_front`, `_agas_back` (JSON strings), `_agas_preview` URL and `_agas_token` for guest links.
- WordPress post author for logged-in design ownership.

## Known gaps - do NOT call this a finished assessment

Required functionality remaining for later milestones:

- QR code tool, 20+ bundled graphics, 10+ Google Fonts, rich text style controls, rotation/opacity/flip/lock/position controls, copy/cut/paste and snap/bleed/rulers with correct physical scaling.
- SVG uploads with sanitization, file originals stored/downloadable separately and draft/published metadata workflow polish.
- Accurate 150+ DPI physical-dimension PNG + PDF with bleed (and vector objects preserved wherever feasible).
- Cart/checkout/account/email preview verification across current WooCommerce theme and chosen checkout style; complete printed design detail views.
- Real template thumbnails for all seed templates, end-to-end mobile testing and full regression suite.
- Two real test orders (one single-sided, one double-sided) have **not** been placed by this package.
- `Need Help` currently points to a demo placeholder address; change it before final submission.
- Fabric.js loads from cdnjs, so online access is needed for this first build.

## Smoke test checklist

1. Open gallery for Outdoor Vinyl Banner; count 6 templates and test search/tag filtering.
2. Select Grand Opening; confirm text and layout load into designer.
3. Change headline and add a rectangle; Undo and Redo.
4. Set 4 x 2 ft; server quote should be $32.00 using default $4/sqft rules.
5. Add Back ($12 default) plus grommets ($5); expect $49.00.
6. Save design, use returned shareable edit link; reload and verify text retained.
7. Continue to Cart; verify customized price and image.
8. Place COD order; in WP Admin > WooCommerce > Orders verify order line item custom details and design link.
9. In Design Templates > Pricing Rules change `sqft_rate` and re-quote; revert it.
10. Repeat from Custom Flag and Yard Sign gallery, then test basic mobile layout.

## Recommended next milestones

- Milestone 2: designer tool completeness + admin template lifecycle.
- Milestone 3: reliable print exports, original uploads, checkout/email previews, two orders, QA and submission.

## Safety

This prototype is designed to run on a local-only port mapped to `127.0.0.1:8099` and demo products/assets. Do not apply it to a production store. Guest design-sharing uses a long bearer token in the edit URL. Uploaded PNGs are public preview assets; do not use private or sensitive customer artwork. The public Save API has basic per-IP rate limiting but is not intended as an Internet-hardened service without further review.


## Milestone 1.1 — Fullscreen layout / initialization safeguards (October 28, 2026)

This patch replaces only the plugin's own **Design Templates** and **Online Design Studio** page templates with an isolated, viewport-width layout; it does not edit the active WordPress theme or WooCommerce files. The studio now has independently scrollable tool/product sidebars, adaptive canvas sizing, and a zoom control relative to its fitted size. The template gallery now uses available desktop width.

The canvas initialization path now logs detailed errors to the browser Console, recalculates the price independently, and has a JSON serialization fallback. This does not prove all product-designer behaviors are fixed; complete the tests below.

### Verification

1. Hard-refresh the Online Design Studio page after overwriting the plugin.
2. Verify WordPress page title, theme navigation, and theme footer are absent **only** on the two plugin pages.
3. Verify the studio fills most of the browser window and the whole product configuration is accessible.
4. Open Grand Opening from each product gallery, change text, save, and test Continue to Cart.
5. Open browser DevTools Console if you see `Designer failed to load`; paste the first stack trace.
6. Verify mobile width with DevTools responsive view; check that the canvas is touch-scrollable and editable.

**Known gaps**: This version does not yet provide the complete requirements from Parts 2–5, including vector PDF/150DPI print export, 20+ clipart items, QR object, full object alignment controls, 10+ fonts, SVG uploads, admin object locks and both required completed test orders. The submission README must disclose these until implemented.
