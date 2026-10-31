# AGAS Design Studio v0.1.15 — Approved Country Flag Scene

Builds on v0.1.14. This is a **presentation-only** hotfix for the `flag-rectangular` mockup profile (country/national flags).

## What changed

- Replaced the previous country-flag background with the approved **blank white flag on a steel pole in a sunlit civic plaza**. The photographic backdrop is bundled locally in `assets/mockups/photos/flag-rectangular.jpg`.
- Updated live overlay coordinates and curved clipping to match the new flag. The silver pole, hardware and flag seams remain visible.
- Preserved wrinkles, drape and highlights through the cloth image's photographic luminance; removed synthetic straight fold strokes that made the fabric look unrealistic.
- Kept the original landscape mapping, so Japan, France, Italy, and Ukraine templates retain the correct flag orientation. User artwork remains dynamic rather than baked into the photograph.
- Updated plugin version/cache key to 0.1.15 and accompanying tests.

## Unchanged

- Business/feather flags, banners, yard signs, template IDs, taxonomy, routing, pricing, print exports, saved design JSON, WooCommerce cart, order data, and the WordPress child theme.
- Country flags are still illustrative mockups; the flat artwork and print outputs remain authoritative.

## Browser verification

1. Hard-refresh **Custom Flag → Country Flags** and select Japan, then France. Both should appear on the same civic-plaza rectangular flag, with different dynamic artwork and visible folds.
2. Select **Business → Company Brand Flag**. It should retain the original shop-side feather-flag mockup.
3. Open Japan in the editor and click **Preview**. The gallery and editor should agree; the flat artwork should remain unchanged.
4. Test on mobile and verify the mockup crops sensibly.

## Automated tests

`node tools/test_country_flag_scene.cjs`
`node tools/test_mockup_profiles.cjs`
`node tools/test_photo_mockups.cjs`
`node tools/test_gallery_preview.cjs`
`node tools/test_editor_features.cjs`

The offline visual QA additionally executed the actual mockup rendering engine against a 2D Canvas implementation with Japanese and French templates, producing 1448x1086 composites.
