# AGAS Design Studio v0.1.13 - Photographic live product mockups

Built on v0.1.12. This is a narrow presentation update. WooCommerce checkout, cart-pricing rules, design IDs, saved template JSON, original-image handling, admin template manager, and print-file generators are unchanged.

## Customer experience

- The **gallery's selected-template panel** now uses a photographic scene for the current product: vinyl banner outside a storefront, feather flag on a commercial sidewalk, or yard sign on a lawn.
- Changing the selected template recomposes its **actual current thumbnail** into that scene. The example wording is not baked into the printable surface.
- The editor's **Preview** continues to show both its accurate **Flat artwork** and the improved **Product mockup**. Front/Back controls recompose the requested side, including unsaved changes in the active editor.
- Uses a canvas perspective mesh, a curved flag silhouette, light/material shading and small banner grommets for photographic context.
- Scenes are **AI-generated illustrative photographs specifically for this demo**, with the sample lettering removed from the print areas. No external photo host or commercial stock library is required.
- If a mockup image cannot load or be composed, the existing v0.1.12 SVG-style mockup remains available. A mockup failure does not block the editor, saved JSON, cart or export.

## How it is implemented

`assets/realistic-mockups.js` defines `window.AGASRealisticMockups.render(stage, artworkUrl)` and draws the local JPG scene plus the selected artwork on a canvas mounted in the existing mockup stage. The gallery and designer call the same renderer. The live photo renderer is a **UI preview**; the original image on the side-by-side Flat artwork panel and the actual print export data remain separate.

Source photography: `assets/mockups/photos/{banner,flag,sign}.jpg`. Product selection comes from the server-side `mockup_kind()` and is provided through the stage's `data-mockup-kind` and `data-mockup-photo` attributes. Existing SVG stage art remains as graceful fallback.

## Tests

- `node --check assets/realistic-mockups.js`
- `node tools/test_photo_mockups.cjs` - verifies source wiring, three product types, selected-art updates and fallback
- `node tools/test_gallery_preview.cjs` - selection/filter/CTA flow unchanged
- `node tools/test_editor_features.cjs`
- `node tools/test_cart_choice_js.cjs`
- `php -l agas-design-studio.php`
- `php tools/test_template_admin.php`
- `php tools/test_cart_regression.php`
- Additional image-based QA: used a real 2D canvas renderer to composite two different artworks for each of the three products, and visually inspected all six resulting mockups. This test uses Skia Canvas inside our QA environment only; it is **not required on the user's Mac or in WordPress**.

## Local acceptance test

1. Install v0.1.13 after backing up v0.1.12. Do not change the child theme.
2. Hard refresh the gallery. Select a Banner template; the photo on the left should show that exact artwork in the storefront scene. Select another Banner template and confirm the printed design changes.
3. Use Product filter to view the Flag and Yard Sign galleries. Select different templates and verify the matching photo scene and artwork.
4. Customize a template, edit visible text and press **Preview**. The Product mockup should display the edited artwork, while the Flat artwork remains unwarped.
5. For double-sided designs, switch Front/Back while Preview is open; both should show the correct selected side.
6. Check responsive display and the cart edit/replace flow again.

## Known limitations

- **Illustrative product preview, not manufacturing proof.** The fabric folding, perspective, real-world mounting geometry, and colors are approximated; customers should rely on Flat artwork for detailed text/content checks and print-ready exports for production.
- Feather flags are a different physical format from ordinary rectangular flags; the scene is a contextual visualization rather than a universal fit for every custom-flag size.
- Background photographs are image assets generated for this demo. They are not photographs from an actual production installation.
- Photographic mockups are browser canvas compositing. Browsers without the required canvas/image APIs will use the prior stylized fallback.
