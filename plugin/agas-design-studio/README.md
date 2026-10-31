# AGAS Design Studio — WooCommerce Skills Assessment

A custom WordPress plugin that lets customers select an editable product template, personalize it with Fabric.js, and order it through WooCommerce. This is an assessment/demo project—not a production print platform.

## Requirements and setup

- Docker and Docker Compose; or WordPress with PHP 8.3, MariaDB/MySQL, and WooCommerce installed.
- WordPress and WooCommerce. The plugin does not modify core files or require any paid product designer.
- An internet connection to load Fabric.js in the editor, plus an optional jsPDF/svg2pdf.js connection in the browser-based print exporter. A bundled dependency-free raster PDF fallback works when those remote PDF libraries are unavailable.
- For the supplied Compose environment, run `docker compose up -d` from the repository root, open `http://localhost:8099`, install WordPress and WooCommerce, then activate **AGAS Design Studio**.
- Create three **published simple products** named **Outdoor Vinyl Banner**, **Custom Flag**, and **Yard Sign**. The plugin identifies the demo products by these names.
- Enable **Cash on Delivery** under WooCommerce > Settings > Payments (including for the configured shipping method) to place test orders.
- The plugin registers a **Design Templates** gallery and **Online Design Studio** editor page on activation and seeds six product-specific canvas templates per product after all three products exist.
- Use the **free Storefront parent theme** plus our **AGAS Printworks Child v1.1.0** theme (`Template: storefront`). The designer continues to use its own fullscreen plugin template.

## Architecture

1. **Store:** WooCommerce catalog, cart, checkout and orders, with server-side validation of custom design prices.
2. **Gallery:** An `agas_template` custom post type, product assignments, categories and preview PNGs. Starter templates are JSON data and thumbnails in `assets/seeds` and `assets/thumbs`.
3. **Editor:** Fabric.js 5 canvas running inside `assets/studio.js`. JSON includes `objects`, `background` and custom metadata such as `agasImagePlaceholder` and `agasOriginalName`.
4. **Design persistence:** Customer designs use a private `agas_design` post with metadata for the chosen product, template, front/back canvas JSON, printing options, share/edit token and low-resolution preview. Public REST save route validates inputs and has a basic rate limit.
5. **Pricing:** Admin-configurable square-foot rate, minimum, back-side surcharge and finishing extras. Server recalculates the price in the WooCommerce cart. Never use a browser-supplied price as the authority.
6. **Order handoff:** Order line item stores design ID and print options. Customer sees preview in modern Cart/Checkout blocks and order detail pages. Admin sees an edit/view link and **Print export center** from the order line item.
7. **Print exporter (v0.1.8):** An admin-only screen takes saved Fabric canvas JSON from an order and renders a PDF or a PNG for each available side in the admin's browser. Fabric.js creates the SVG; svg2pdf.js + jsPDF attempt vector PDF output. If the PDF libraries are unavailable or SVG conversion fails, an in-plugin PDF generator embeds a 150-DPI JPEG rendering at true physical page dimensions. Raster fallback is not vector artwork. PNGs are rendered at physical dimensions × 150 pixels/inch with a pHYs density marker. A 0.125-inch colored bleed surrounds the chosen print size. Uploaded PNG/JPG originals can also be downloaded separately from this admin screen if their base64 data is preserved in the saved Fabric JSON.

### Print-export filename

`OrderID_Line_Product_Size_Side.pdf`, with a matching `.png` filename, e.g. `41_1_Yard_Sign_3x2ft_Front.pdf`.

**Preflight:** PDFs must be inspected at actual page size and image fidelity before print. SVG-to-PDF font support is limited and a PDF may have a raster fallback. The output is a demo and is not guaranteed production-qualified or color-managed. Very large 150-DPI rasters may exceed browser memory; the demo caps PNG output at 80 million pixels. Uploaded images may themselves have lower native resolution than the desired print resolution. The current exporter does not retain the generated PDF/PNG in permanent server storage—downloads are generated on demand when an admin clicks a button.

## Testing the required customer flow

1. Open **Design Templates**, choose a product and a design (or Start from blank).
2. Edit text, shapes, color or a logo slot; Save and use Continue to Cart.
3. Confirm the customized artwork thumbnail, dimensions and server price in Cart/Checkout.
4. Complete a Cash on Delivery order. Demonstrated: single-sided Yard Sign, 3×2 ft, Order #41, $24 before other charges.
5. Repeat with **Add back side** selected, add content to Back, and complete a second order. This test still requires confirmation on the target installation.
6. In WooCommerce > Orders, open the order and click **Print export center** from the design's line item. Download Front PDF and PNG (and Back if ordered), and any customer-uploaded originals. Inspect all outputs and pixel dimensions.

## Assessment scope and known limitations

Working in observed screenshots: 3 products; original gallery; canvas editing and saving; first order; cart/checkout artwork preview; server pricing. v0.1.4–v0.1.5 introduced distinctive templates, object clipboard actions and image placeholders; browser regression testing is required. v0.1.6 added admin export code, but it too requires a real browser acceptance test.

The v0.1.11 implementation includes 12 Google Fonts, 24 categorized original vector cliparts, QR, restricted SVG uploads, richer text controls, snap/bleed/rulers and a Draft/Published admin template designer. **These newly added features still need a full Chrome/mobile WooCommerce acceptance test.** PDF vector fidelity and image sharpness depend on file content and browser libraries. Original PNG/JPG downloads reconstruct embedded JSON data; original SVG upload bytes are not separately preserved. Durable print-file storage, production preflight and complete mobile QA remain outstanding.

## Safety and deployment

- Keep `.env`, database backups, customer data and personal credentials out of Git.
- The current Docker Compose configuration binds WordPress to loopback only. It is **not** a public staging link. Submission requires either a separately deployed staging store or a complete importable site export (including uploads and the database).
- Database state, published products and previous order history are **not** stored in the Git plugin directory. Include a database/media site export for review; source Git alone cannot reproduce the populated demo.
- Send reviewer credentials separately from the repository and README.

## ERP integration plan (Priority or similar)

After WooCommerce successfully creates the order, collect order ID, customer/ship-to data, SKU, quantity, print dimensions, side count, finishing options, design identifiers and export-file references. A queued worker prepares validated artwork, pushes order metadata to the ERP using authenticated APIs, records the external ERP order ID and retries failed webhook/API calls idempotently. Order/production states should be synchronized with logged, permission-controlled status updates. Never send unverified or temporary browser-generated file links as production files; first store durable files in restricted object storage.

## If given five more days

Harden the editor and admin template workflows with extensive mobile/accessibility tests; add render queue with durable PDF/PNG/original-file storage, automated PDF preflight and source-image DPI warnings; test Chrome/Safari/mobile, order email and My Account, checkout block regressions and all supported sizes; deploy a reviewer-accessible staging site with CI and backup/restore automation.

## v0.1.7 PDF library fallback

The print center attempts jsPDF/svg2pdf for vector output. Some development networks block those third-party scripts; the PDF button now remains usable through a bundled `assets/offline-pdf.js` generator and full-size JPEG at 150 DPI. Inspect the generated PDF page size and appearance. Do not claim the fallback PDF has vector text or that its bleed is fully print-preflighted.

## v0.1.8 cart + PNG correctness fix

- Custom-design add-to-cart now posts to a frontend URL so WooCommerce restores and preserves the existing cart session; the legacy admin-post handler is retained for cached tabs.
- Distinct saved designs remain separate cart items; re-adding a design already in the cart updates its existing line.
- Pricing, cart labels, and order item metadata are refreshed from server-saved options to prevent stale front/back charges after editing.
- The PNG export reads the encoded pixel dimensions, retries if the browser downsizes `toBlob()`, and refuses a download if the final image does not have the required full print pixels. The `pHYs` resolution field is added to valid PNG output. This is a browser-only fallback, not a production image preflight.
- For 3×2 ft + 0.125-inch bleed on every edge, the target PNG is 5438×3638 at ~150 DPI. Verify the actual local download with `sips -g pixelWidth -g pixelHeight -g dpiWidth -g dpiHeight filename.png` on macOS. Chat attachments may be resized during upload.

### QA gate after installing v0.1.8

1. In a single browser session, add Yard Sign (front, $24) and Custom Flag (front/back, $36). The cart must retain two separate lines and show $60 before taxes/shipping.
2. Edit the saved Yard Sign design, activate Back, and click Continue to Cart. The cart should still contain two lines, now $36 + $36 = $72.
3. Download 3×2 ft front PNG from an order and check the actual pixel dimensions/DPI in Downloads. The exporter now fails with a visible error rather than sending an undersized file.
4. Confirm checkout shows the same two prices and both design references, then place a Cash on Delivery test order.

### Local mock regression test

Run `docker compose exec -T wordpress php /var/www/html/wp-content/plugins/agas-design-studio/tools/test_cart_regression.php </dev/null` from the project root. This tests pricing and option synchronization with mock cart items; it does not replace real browser testing with WooCommerce.

### v0.1.9 usability and cart editing

The design editor now offers **Edit selected text**, a list of text layers, and an **Edit on canvas** action (Enter also enters inline editing when a text object is selected). These controls make text editing possible without guessing the double-click gesture. The cart page displays an **Edit your designs** panel with one edit link per customized line. Each link includes the existing saved design token and returns to the editor. **Back to cart** is offered on the editor, and **Continue to Cart** resaves the same design so WooCommerce updates its existing line rather than duplicating it. The product name continues to link to the regular product page.

The modern WooCommerce Cart block does not expose a supported per-item action slot; the separate cart panel is intentional. The older classic cart template uses its standard `woocommerce_before_cart` hook. See `README-UPDATE-v0.1.9.md` for acceptance tests.

### v0.1.10 - Replace an existing cart design or add another version

When editing an item from **Cart > Edit design**, the customer is now asked whether to **Replace existing cart item** or **Add as a separate cart item**. The choice also appears on **Save** to prevent an accidental overwrite before Continue. Replacing updates only the selected cart line. Adding separately creates a new private saved-design record and unique access token, so changes to the new item do not alter the original artwork, options or price. **Cancel** leaves the editor untouched. Fresh templates opened from the gallery still follow the ordinary add-to-cart flow without a prompt.

A saved separate copy can be edited further; Continue adds that new design to the cart. Cart link URLs include a cart-line key that the server verifies for replacements. The server rejects invalid action modes, missing replacement targets and attempts to use the same saved-design record as an independent cart line. See `README-UPDATE-v0.1.10.md` for the browser acceptance checklist.

Mock tests: `php tools/test_cart_regression.php`, `php tools/test_cart_choice.php`, and `node tools/test_cart_choice_js.cjs`. These do **not** replace browser testing on the local WooCommerce store.

## v0.1.11 - expanded designer and admin template workflow

v0.1.11 adds 12 Google Fonts, rich text controls, 24 original categorized vector cliparts, offline QR generation, safe-subset SVG uploads, background-image control, front/back thumbnails, bleed/ruler/center guides, zoom dropdown and Help. WordPress admins can create templates directly in the designer, assign to products, choose Draft/Published, save front/back JSON and thumbnails, duplicate drafts and protect selected objects against deletion. See `README-UPDATE-v0.1.11.md` for the precise feature list, security limitations, QA commands, and end-to-end test steps. Version-specific limitations and browser acceptance steps are listed in `README-UPDATE-v0.1.11.md`.

## v0.1.12 gallery discovery + two-view preview

- Large, centered gallery search with Product and Category filters beneath it; two template cards per desktop row.
- Click a tile to preview a banner, flag or yard-sign illustrated mockup on the gallery's left. The **Customize** action opens the selected JSON template in the existing designer.
- Designer Preview shows a product-context mockup and the original flat canvas side-by-side, with front/back selectors when enabled. This is a display-only view and **does not change saved artwork or print exports**.
- Original local vector scenes live at `assets/mockups/{banner,flag,sign}-scene.svg`. No paid assets or third-party mockup service is required.
- See `README-UPDATE-v0.1.12.md` for verification and known limitations.

## v0.1.13: Live photographic product mockups

Gallery selection and editor Preview now show dynamically composited photographs for Banner, Flag, and Yard Sign. They use the actual chosen template/current design and never replace the flat artwork or manufacturing exports. Assets are bundled locally. See `README-UPDATE-v0.1.13.md`.

### v0.1.14 — Contextual product/category mockups

Photographic mockups now resolve by **physical product type plus category**, with a per-template admin override stored as `_agas_mockup_profile`. Examples: country flags use rectangular flags on poles, corporate flags use feather flags; event banners differ from storefront banners; real-estate lawn signs differ from parking-post and event-directional signs. The original Fabric.js design JSON and print/export pipeline are unchanged. Full mapping, testing and visual limitations: `README-UPDATE-v0.1.14.md`.


## v0.1.15 — Approved country flag mockup environment

The rectangular/country-flag scene now uses a natural, photographed-looking white flag outside a civic plaza with dynamic artwork compositing and preserved fabric lighting. Business feather flags are unchanged. See `README-UPDATE-v0.1.15.md`.


## Editor workspace update (v0.1.16)
The studio uses an icon-only insertion rail on the left and a selection-sensitive, icon-first property toolbar above the Fabric.js canvas. The product setup panel stays on the right. Add Back sits with side previews. Finishing options are mutually exclusive (None / Grommets / Pole pocket), validated on the server. See `README-UPDATE-v0.1.16.md` for mobile layout and regression checks.
