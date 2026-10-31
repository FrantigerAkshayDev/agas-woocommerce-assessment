# AGAS Design Studio v0.1.12 — Template discovery and product mockups

This release builds on v0.1.11, without changing saved design JSON, template IDs, cart behavior, pricing calculations, REST routes or print exporters.

## Customer gallery

- A large, centrally aligned, accessible search field, with Product and Category filters below it.
- Live case-insensitive title/category filtering, a clear action, no-results messaging, and a results counter.
- Two design tiles per row in the primary gallery; adapts for smaller viewports (a single column below 370px).
- Clicking **a template tile** selects it and shows a staged product mockup on the left **without opening the editor**. A separate **Customize** action loads that exact saved template.
- Preview stage uses one of three original illustrated product contexts: banner on a fence, flag on a pole, or yard sign on a lawn. These SVG scene files are bundled in `assets/mockups/` and require no CDN or external photos.
- Selection is keyboard accessible via a proper button with `aria-pressed`. Search updates are announced via `role=status`.

## Editor Preview

- The existing Preview button now opens two **simultaneous** visual views: **Product mockup** and **Flat artwork**.
- When a back side is enabled, Front/Back controls let customers inspect either side without switching the underlying editing canvas or modifying its undo history.
- Preview images omit selected-object controls and temporary placeholders. Source JSON and print exports stay untouched.
- The mockup is an **illustrative rendering**, not the full-size production artwork. The flat design is the source for order artwork and print export.
- Read-only order preview mode can use the Front/Back preview selectors, without enabling editing.

## Test coverage

- `php -l agas-design-studio.php`
- `node --check assets/gallery.js`
- `node --check assets/studio.js`
- `node tools/test_gallery_preview.cjs` — gallery selection, search, category, clear, empty state, product links, editor preview markup
- `node tools/test_editor_features.cjs`
- `node tools/test_cart_choice_js.cjs`
- `php tools/test_template_admin.php`
- `php tools/test_cart_regression.php`
- ZIP CRC validation and 3-product PHP-rendered gallery fixture verification

## Browser acceptance checks

1. Hard-refresh `/agas-templates/` and confirm the large search field and Product/Category dropdowns.
2. Confirm exactly two template tiles in each desktop row.
3. Click one tile: it should highlight, update the left mockup/name/category, and **stay in the gallery**.
4. Click **Customize this design**: the correct template must load in the designer.
5. In the designer, click **Preview**: check mockup and flat artwork side-by-side, then Exit Preview.
6. Enable a back design and confirm that both Preview side selectors work without modifying the saved design.
7. Repeat with Flag and Yard Sign products, and check narrow/mobile widths.
8. Add a customized design to the cart to confirm the prior checkout flow is unaffected.

## Known limitations

- Product mockups are original stylized illustrations, not photorealistic images or manufacturing proofs.
- Mockup warp on flags is illustrative; it does not simulate fabric physics.
- This release does not implement the separate requested Canva-like editor rearrangement or guided onboarding tour. Those are tracked as separate review items.
- If Fabric.js or images cannot be drawn, the editor shows a preview error and retains the working editor; PDF and PNG print exports are independent of the UI mockup.
