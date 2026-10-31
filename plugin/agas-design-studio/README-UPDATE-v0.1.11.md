# AGAS Design Studio v0.1.11 - Mandatory editor + template-manager milestone

This is an additive update to v0.1.10. WooCommerce pricing, guest edit links, cart replace/add behavior, order metadata, and PDF/PNG print exporters remain in their previous modules.

## Newly implemented editor controls

- 12 font choices from Google Fonts (plus five local fallbacks), loaded through Google's CSS API when internet access is available.
- Subheading text; italic, underline, text align, letter spacing, line spacing; left/right/top/bottom alignment of selected objects.
- 24 self-authored, vector, royalty-free geometric cliparts with Business / Events / Nature / Symbols & Safety filters.
- Offline QR generator (`assets/vendor/agas-qrcode.js`), producing a scannable vector QR from an HTTP(S) URL; QR library licensing is in `assets/vendor/LICENSE-QR.txt`.
- PNG/JPG image backgrounds, plus remove-background control. PNG/JPG/SVG image uploads through existing buttons/drag-and-drop. SVG is **restricted to simple local vector shapes**: scripts, CSS, external references, use/image/foreignObject/animations are deliberately rejected.
- Front/back preview thumbnails, an explicit Add Back surcharge label, width/height rulers using the chosen unit, a 0.125-inch bleed guide, snap-to-center visual guides, zoom slider + percentage dropdown and functional in-editor Help.
- Safety/bleed/snap lines are DOM editor overlays, not Fabric canvas objects, and therefore are excluded from print exports.

## Admin template-manager workflow

1. Log in as a user with `manage_woocommerce` capability.
2. WordPress admin > Design Templates > **Create in Designer**.
3. Choose one of the three products, create or upload artwork in the SAME canvas editor, enter title/category, check one or more assigned products, and save as **Draft** or **Published**.
4. Admin Design Templates list includes preview, product assignment, category, and status columns. Existing template rows have the **Duplicate template** action. Duplicates start as Draft.
5. Select an object and choose **Lock object** to freeze it as an administrator-controlled lock. Alternatively choose **Protect from deletion**; customers can continue editing text in a deletion-protected, otherwise-unlocked object.
6. Publishing makes the template visible in its assigned product gallery; Draft remains admin-only. The main canvas JSON and optional back-side JSON are saved in WP post meta.

## Compatibility and limits

- The custom editor still uses Fabric.js 5.3.1 from cdnjs; Google Fonts require access to fonts.googleapis.com/fonts.gstatic.com. The QR library is bundled locally.
- Print PDF fallback may rasterize text/graphics; Google Fonts may fall back if unavailable and are not fully embedded in the SVG-to-PDF exporter. Print-preflight and font fidelity remain production limitations.
- SVG imports support clean plain paths/shapes rather than unrestricted Illustrator/CSS SVG. Saved vector objects are editable, but unlike PNG/JPG, the *original SVG upload bytes* are not yet separately stored in the admin originals list. Uploaded PNG/JPG design objects and PNG/JPG backgrounds are available to the existing originals extractor.
- The admin status/save/QR/parser tests use isolated mocks or browser components; they do not replace end-to-end testing inside the user's local WordPress instance.
- Current template model still uses a single canvas per product side, no full proof-approval, and no durable server PDF generation pipeline.
- In a real deployment, sanitize and store all uploaded originals as restricted media attachments, audit upload processing, and implement queue-based print rendering.

## Verification commands (run from project root)

```bash
node --check plugin/agas-design-studio/assets/studio.js
node --check plugin/agas-design-studio/assets/vendor/agas-qrcode.js
node plugin/agas-design-studio/tools/test_editor_features.cjs
node plugin/agas-design-studio/tools/test_cart_choice_js.cjs

docker compose exec -T wordpress php -l /var/www/html/wp-content/plugins/agas-design-studio/agas-design-studio.php </dev/null
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/agas-design-studio/tools/test_template_admin.php </dev/null
docker compose exec -T wordpress php /var/www/html/wp-content/plugins/agas-design-studio/tools/test_cart_regression.php </dev/null
```

## Browser acceptance (real WooCommerce, required before submission)

- Select Google Font `Montserrat`, edit text, italicize and align it, Save and reopen; verify changes persist.
- Add one clipart from each category; add a QR for a known test URL and scan it from a screen/print preview with a phone.
- Upload one PNG/JPG image and one plain SVG; verify SVG paths on canvas, reject a test SVG containing script/event attributes, and verify print output.
- Upload a PNG background; check that it remains on Front after editing Back and switching back.
- Create a new admin template in Draft; confirm it is hidden in the customer gallery; publish and confirm it appears. Edit again, duplicate and confirm no existing customer artwork changes.
- Verify admin deletion-protected text can still be modified by the customer but cannot be deleted. Verify admin-locked logo cannot be repositioned or unlocked by customers.
- Test front/back side previews, dimensions, snapping, Help overlay and mobile designer.
- Regression: two products in cart, edit/replace and add-as-separate, server pricing and COD checkout; download PDF and 150-DPI PNG from an order.
