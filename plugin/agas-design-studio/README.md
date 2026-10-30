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
- Use a free WordPress theme. Twenty Twenty-Five works; the designer bypasses narrow theme layout using its own plugin template. If customizing a classic theme, create a child theme rather than modifying the parent.

## Architecture

1. **Store:** WooCommerce catalog, cart, checkout and orders, with server-side validation of custom design prices.
2. **Gallery:** An `agas_template` custom post type, product assignments, categories and preview PNGs. Starter templates are JSON data and thumbnails in `assets/seeds` and `assets/thumbs`.
3. **Editor:** Fabric.js 5 canvas running inside `assets/studio.js`. JSON includes `objects`, `background` and custom metadata such as `agasImagePlaceholder` and `agasOriginalName`.
4. **Design persistence:** Customer designs use a private `agas_design` post with metadata for the chosen product, template, front/back canvas JSON, printing options, share/edit token and low-resolution preview. Public REST save route validates inputs and has a basic rate limit.
5. **Pricing:** Admin-configurable square-foot rate, minimum, back-side surcharge and finishing extras. Server recalculates the price in the WooCommerce cart. Never use a browser-supplied price as the authority.
6. **Order handoff:** Order line item stores design ID and print options. Customer sees preview in modern Cart/Checkout blocks and order detail pages. Admin sees an edit/view link and **Print export center** from the order line item.
7. **Print exporter (v0.1.7):** An admin-only screen takes saved Fabric canvas JSON from an order and renders a PDF or a PNG for each available side in the admin's browser. Fabric.js creates the SVG; svg2pdf.js + jsPDF attempt vector PDF output. If the PDF libraries are unavailable or SVG conversion fails, an in-plugin PDF generator embeds a 150-DPI JPEG rendering at true physical page dimensions. Raster fallback is not vector artwork. PNGs are rendered at physical dimensions × 150 pixels/inch with a pHYs density marker. A 0.125-inch colored bleed surrounds the chosen print size. Uploaded PNG/JPG originals can also be downloaded separately from this admin screen if their base64 data is preserved in the saved Fabric JSON.

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

Some assessment requirements are **not yet fully met**: 10+ Google Fonts, 20+ categorized royalty-free clipart graphics, QR generator, SVG uploads, complete text alignment/letter/line spacing controls, full bleed/safety snapping workflow, sides-preview thumbnails, complete mobile QA, and a complete template-management UX. The admin can work with a template post and use the shared designer; verify the end-to-end admin create/duplicate/edit/publish flow. PDF vector fidelity and image sharpness depend on file content and browser libraries. Original image downloads currently reconstruct data embedded in the design JSON rather than maintaining a separate production asset store.

## Safety and deployment

- Keep `.env`, database backups, customer data and personal credentials out of Git.
- The current Docker Compose configuration binds WordPress to loopback only. It is **not** a public staging link. Submission requires either a separately deployed staging store or a complete importable site export (including uploads and the database).
- Database state, published products and previous order history are **not** stored in the Git plugin directory. Include a database/media site export for review; source Git alone cannot reproduce the populated demo.
- Send reviewer credentials separately from the repository and README.

## ERP integration plan (Priority or similar)

After WooCommerce successfully creates the order, collect order ID, customer/ship-to data, SKU, quantity, print dimensions, side count, finishing options, design identifiers and export-file references. A queued worker prepares validated artwork, pushes order metadata to the ERP using authenticated APIs, records the external ERP order ID and retries failed webhook/API calls idempotently. Order/production states should be synchronized with logged, permission-controlled status updates. Never send unverified or temporary browser-generated file links as production files; first store durable files in restricted object storage.

## If given five more days

Complete the missing editor tools and SVG support; finish administrator template status/category/multi-product assignment workflows and previews; add render queue with durable PDF/PNG/original-file storage, object-lock rules, automated PDF preflight and source-image DPI warnings; test Chrome/Safari/mobile, order email and My Account, checkout block regressions and all supported sizes; deploy a reviewer-accessible staging site with CI and backup/restore automation.

## v0.1.7 PDF library fallback

The print center attempts jsPDF/svg2pdf for vector output. Some development networks block those third-party scripts; the PDF button now remains usable through a bundled `assets/offline-pdf.js` generator and full-size JPEG at 150 DPI. Inspect the generated PDF page size and appearance. Do not claim the fallback PDF has vector text or that its bleed is fully print-preflighted.
