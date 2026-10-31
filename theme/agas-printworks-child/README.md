# AGAS Printworks Child - WooCommerce customer storefront v1.1.0

**Architecture:** Free parent WordPress theme **Storefront** (`storefront` theme slug) plus the custom **AGAS Printworks Child** theme. Unlike v1.0.0, this satisfies the assessment's explicit parent + child theme requirement. All bespoke HTML, CSS, JS, mock artwork, product gallery navigation and WooCommerce presentation were created specifically for the AGAS exercise; this is not a downloaded pre-made child design.

## Pre-requisites

- Latest compatible WordPress/WooCommerce; active AGAS Design Studio plugin v0.1.10 or later.
- Install the **free Storefront** theme first (Appearance > Themes > Add New > search Storefront > Install). **Do not activate Storefront** if the current active theme is functioning; the child will be activated separately.
- Install this child theme ZIP via Appearance > Themes > Add New > Upload Theme and activate **AGAS Printworks Child**.

The ZIP intentionally does **not** bundle Storefront; the parent is from the official WordPress.org theme directory. WooCommerce documents Storefront as free and confirms that `Template: storefront` links the child to the parent.

## Integration details

- Child overrides `header.php`, `footer.php`, `front-page.php`, `woocommerce.php`, and other site templates; Storefront provides WooCommerce base compatibility.
- `functions.php` uses `get_stylesheet_directory_uri()` for assets (not the parent template directory) and removes Storefront's extra WooCommerce outer wrappers on shop/product pages. Storefront itself loads the child stylesheet.
- The design gallery and fullscreen editor are **plugin-owned standalone views**; this theme does not override the editor canvas.
- Actual pricing, cart edits, saved artwork and order exports remain owned by AGAS Design Studio/WooCommerce.

## Test after activation

Homepage, Shop, all three product pages, corresponding galleries, designer, Cart, Checkout, My Account; desktop and mobile at widths 390 / 768 / 1440px. Confirm the free Storefront parent is installed and this child is active. Test two different saved products in the cart and preview/export functions.

## Rollback

Appearance > Themes > reactivate the previous theme. This theme has no database migrations or product/order deletion paths.

## Source/Git layout

Place `agas-printworks-child` under `theme/agas-printworks-child/` in the assessment repository. Commit that folder. The parent Storefront is a declared dependency, not something to commit into project source.
