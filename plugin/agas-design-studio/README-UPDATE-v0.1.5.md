# AGAS Design Studio v0.1.5 — Logo placeholder interaction

## Changes

- Replaces the editable text placeholder in the seeded Company Brand Flag with a structured Fabric.js `group` marked `agasImagePlaceholder`.
- Clicking the empty placeholder opens an image chooser; an **Add logo placeholder** tool lets users insert the same interactive slot in any design; new **Upload logo to this placeholder** and **Replace selected image** actions are also available.
- PNG/JPG files (up to 1 MB in this milestone) fit within the original slot without distortion; uploaded image becomes a Fabric image object (`agasLogoSlot`) that can be moved, resized, copied, or replaced.
- Slot position and replacement image survive normal canvas JSON saving and WooCommerce checkout. Empty placeholder instructions are hidden in Preview and in the saved order-preview thumbnail.
- Updates only the *untouched* seeded Company Brand Flag canvas in WordPress; templates edited by an administrator and saved customer designs remain unchanged. The revision option is `agas_ds_logo_slot_revision`.
- Maintains the existing cart, order, pricing and template handlers.

## Known requirements still missing (do not claim complete)

- Uploaded originals are **not separately persisted** by this release; PNG/JPG images are currently embedded as data URIs in the saved design JSON. The assessment also requires SVG uploads and independent original-file downloads.
- Admin print-ready per-side true-size PDF and >=150-DPI PNG are not implemented.
- Other Part 2 requirements such as QR codes, 20+ clipart, 10+ Google Fonts, advanced text controls, full alignment controls and 20-step history need end-to-end testing/implementation.
- This build has PHP/JS syntax checks and JSON marker tests; it has **not** been run inside the user's WordPress/Chrome environment.

## Smoke test

1. Back up the WordPress DB and plugin directory; install the v0.1.5 files over the existing plugin.
2. Open Gallery -> Custom Flag -> Company Brand Flag -> Customize; hard-refresh.
3. Click the dashed **CLICK TO UPLOAD YOUR LOGO** placeholder.
4. Select a small PNG/JPG (<1 MB). The image should replace the slot at the same location and fit within its bounds.
5. Select the image, click **Replace selected image**, choose another image.
6. Test Move/Resize, Undo/Redo, Save, then reopen via the saved design link. Verify image remains.
7. Check a no-logo Preview: the empty placeholder should not be visible in the saved preview.
8. Re-test a custom Yard Sign add-to-cart and verify the $24 price is unchanged.
