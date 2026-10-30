# AGAS Design Studio v0.1.3 — Cart preview and currency hotfix

This targeted update builds on v0.1.2 and fixes two issues observed on October 30:

1. The editor showed the encoded WooCommerce currency HTML entity (`&#36;`) instead of `$`. The pricing REST endpoint now returns a decoded currency symbol for `textContent` rendering. Numerical calculations are unchanged.
2. The cart used the WooCommerce **Cart block**, which does not invoke the older `woocommerce_cart_item_thumbnail` hook. The new `woocommerce_store_api_cart_item_images` filter returns the saved design preview where available; the classic-cart filter is retained.
3. Adds a saved design preview to order-item details / HTML emails through the supported order-item metadata hook, and a preview URL in plain-text emails.

The plugin still needs real browser verification of previews in the Cart and Checkout blocks, order email, and My Account. This is **not** a finished print-ready export solution, and other mandatory assessment features remain outstanding. The template and product content are unchanged.

## Installation (backup first)

Unzip the package, copy the `agas-design-studio/` directory contents into `~/AGAS/agas-woocommerce-assessment/plugin/agas-design-studio/` after backing up the existing directory. Do not replace the database.

## Checks

- Editor price for 3 x 2 ft, front only: `$24.00`, without `&#36;` text.
- Cart block thumbnail: custom purple design preview (not the Yard Sign stock photo).
- Order price remains `$24.00` before tax/shipping, product options preserved.
- Place COD test order and check order item artwork preview.
- If thumbnail is unchanged, verify `_agas_preview` exists for the saved design, that the image URL loads, and that the Store API is running WooCommerce 9.6+.

Reference: https://developer.woocommerce.com/2025/01/16/new-woocommerce-blocks-cart-item-image-filter/

Preview URLs are restricted to the local WordPress uploads base URL, so local Docker URLs such as `http://localhost:8099/wp-content/uploads/...` work without SSRF URL checks that reject private addresses.
