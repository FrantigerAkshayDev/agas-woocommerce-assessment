# AGAS Design Studio v0.1.17 — Sidebar template browsing and responsive Continue

## Changes

### 1. Browse templates inside the editor

- Clicking the **Templates** icon opens a Canva-inspired two-column thumbnail browser in the existing left insertion sidebar.
- The **Browse templates** button focuses the search field; it no longer navigates away from the editor.
- Search by template name/category and filter by category. The list includes a **Start from blank** option.
- Only published, nonlegacy templates assigned to the current WooCommerce product appear.
- The initial page sends only template metadata (ID, title, category, thumbnail), not all canvas JSON.
- Selecting a template fetches the front/back JSON through `GET /wp-json/agas-design/v1/templates/<id>?product_id=<product>`.
- The endpoint returns 404 for unpublished, invalid, hidden or differently assigned templates.
- A confirmation is required before replacing existing artwork. If canceled, nothing changes.
- Selection updates the canvas without page reload, updates the front/back state and mockup context, and preserves current product dimensions and finishing settings. The save workflow remains unchanged.
- On a narrow screen, selecting a template closes the tool drawer to reveal the canvas.
- **Open full gallery** remains an optional link for customers who want the original gallery experience.

### 2. Footer action visibility on phones and tablets

- At <=1100px, the footer uses a bounded grid rather than horizontally scrolling controls.
- At <=740px, Zoom/Help occupy the top line and Price/Continue the lower line.
- The Continue CTA does not require horizontal scrolling; safe-area padding is included for mobile devices.
- No changes to WooCommerce cart, pricing, template JSON storage or print exports.

## Tested

```bash
node --check assets/studio.js
node --check assets/template-sidebar.js
php -l agas-design-studio.php
node tools/test_template_sidebar.cjs
php tools/test_template_sidebar.php
node tools/test_editor_layout.cjs
node tools/test_editor_features.cjs
node tools/test_mockup_profiles.cjs
node tools/test_gallery_preview.cjs
node tools/test_cart_choice_js.cjs
php tools/test_cart_regression.php
php tools/test_template_admin.php
```

Browser test in the QA fixture used Chromium at 1024, 820, 768, 744, 430, 390, and 320px. Each Continue bounding box was entirely within the viewport, with no document-level horizontal overflow. Sidebar search, filters, selection, blank start, and cancellation passed. This does **not** substitute for final verification in the actual WordPress site on Safari/Chrome and a physical iPad.

## Local browser acceptance

1. Hard-refresh the editor, click **Templates**, and verify the thumbnails appear in the left sidebar.
2. Search `Japan`, filter by `Country Flags`, and verify only relevant templates remain.
3. Select another template and cancel the confirmation; the existing artwork should remain unchanged.
4. Select a different template and confirm. Check the canvas and editor Preview, then Save and Continue to Cart.
5. Verify **Start from blank** replaces the artwork only after confirmation.
6. Use iPad portrait/landscape and mobile sizes in device emulation. Verify Continue is always visible and clickable, even with the template drawer open and when prices update.
7. Recheck double-sided design, the replace/add choice, and finishing exclusivity.

## Deployment and limitations

- Install on top of v0.1.16 only. Back up the plugin and DB first; no migrations are needed.
- The browser template panel needs the existing editor's Fabric.js script to load. The backend REST endpoint must work under the site's current permalink configuration.
- A replacement prompts before changing the artwork, but it is not automatically saved: click Save or Continue when ready. In admin-template mode, switching changes the *source artwork*, while Save still updates the currently edited template record; the warning explicitly states this.
- The template canvas assumes the standard 900x600 seed coordinate system and scales to the current print canvas. Complex externally authored templates with different native dimensions may require an explicit canvas-dimension schema in a later release.
