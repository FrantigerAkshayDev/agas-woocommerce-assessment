# AGAS Design Studio v0.1.8 — Cart and PNG correctness hotfix

## Fixes

- Sends **Continue to Cart** through a frontend WordPress request so WooCommerce restores its existing cart session before appending a customized product. Existing v0.1.7 admin-post requests remain supported for cached editor tabs.
- Preserves multiple separate designs in one cart, including separate designs for the same WooCommerce product. Re-adding the **same saved design ID** updates that cart item instead of duplicating it.
- Reads options (including Front + Back) from saved server-side design metadata for cart price recalculation, cart display, and final order metadata. Edits to a saved design won't leave its cart price behind.
- Generates the PNG with the expected full pixel canvas, checks the PNG's actual IHDR dimensions, retries direct encoding when browser `toBlob` produces a smaller image, and refuses undersized downloads. Adds PNG `pHYs` DPI metadata as before.

## Regression tests

1. Existing single-sided order remains $24.00 at 3×2 ft with no extras (rate $4/sq.ft; minimum $15).
2. New double-sided 3×2 ft design is $36.00 in editor AND cart (back add-on $12).
3. Cart contains Yard Sign and Custom Flag simultaneously, with individual previews and prices.
4. Edit a design already in cart and select Back: cart and checkout update to $36 when revisited.
5. Re-adding the same saved design updates its existing line without doubling quantity.
6. 3×2 ft with 0.125-inch bleed exports a PNG of 5438×3638 pixels, about 150 DPI. Confirm locally with `sips` or an image inspector. Download may fail with an explicit message if the browser cannot encode that size.
7. Checkout records the matching design options on each order line, both single- and double-sided.

## Boundaries

- Export remains browser-dependent at larger print sizes. It fails loudly rather than mislabeling a low-resolution image.
- PDFs retain the existing v0.1.7 raster fallback; vector fidelity requires separate production preflight.
- WordPress products, orders, templates, and saved designs are not migrated or deleted.
