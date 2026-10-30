# AGAS Design Studio v0.1.4 — Product templates & object editing

This is an **incremental update to v0.1.3**, not the final assessment submission. It preserves the existing WordPress + WooCommerce cart, pricing and checkout integration. It does not add print-ready PDF/PNG export or complete every mandatory editor feature.

## What changed

- **18 distinct product-specific templates** (6 per product), each with its own real thumbnail and Fabric.js canvas JSON:
  - **Outdoor Vinyl Banner:** Grand Opening, Flash Sale, Music Festival, Now Hiring, Coming Soon, Community Fundraiser.
  - **Custom Flag:** France Flag, Japan Flag, Italy Flag, Ukraine Flag, Company Brand Flag, Event Flag.
  - **Yard Sign:** Open House, For Sale, Sold, Welcome Home, Reserved Parking, Event Entrance.
- Product gallery categories now derive from templates available for the selected product, so *Country Flags* appears only when relevant.
- **Copy, Paste, Duplicate, Cut, Delete** object actions in the toolbar; **Cmd/Ctrl+C, V, D, X** shortcuts; **Delete/Backspace**; existing Undo/Redo shortcuts retained. This is an editor-local clipboard, not the operating system clipboard. Select individual objects or Shift-select multiple objects.
- Selected-object tools: rotate ±15°, flip horizontally/vertically, center, opacity and lock/unlock. Locked objects cannot be moved, deleted, copied or duplicated until unlocked.
- Undo records a single snapshot for paste/cut/duplicate operations and keeps the existing history limit (25 snapshots, including initial state).

## Migration behavior (automatic, non-destructive)

On the first WordPress page request after updating:

1. The plugin creates 18 new product-specific template posts with a unique `_agas_seed_key` and JSON canvas data. Thumbnails are shipped with the plugin.
2. **Only unmodified original v0.1.3 demo templates** (matching the original JSON exactly and lacking an admin-generated thumbnail) receive `_agas_legacy_hidden=1`, so they disappear from the public gallery. They are **not deleted or unpublished**; saved designs and old edit links continue to work.
3. Previously edited old templates are **not hidden or overwritten**. Those may remain visible in addition to the six new product presets.
4. The migration sets `agas_ds_seed_revision=v0.1.4` to avoid duplicating templates on future page loads.
5. An admin who edits and saves a legacy template in the designer will unhide it automatically.

Because the migration inserts new WordPress posts and modifies metadata, **back up the local database before applying the update**. Use a full Docker database dump or an equivalent WordPress backup. Do not delete/reset database volumes.

## Installing into the existing Docker store

Back up the database and `plugin/agas-design-studio` folder first, then merge the ZIP's `agas-design-studio/` directory over `~/AGAS/agas-woocommerce-assessment/plugin/agas-design-studio/`. No WordPress reinstall, database reset or plugin reactivation is required. Run `php -l` in the WordPress container and `node --check` on `assets/studio.js`; hard-refresh the gallery and editor after installation.

## Verification

- Select **Outdoor Vinyl Banner** in the gallery: six banner-oriented visual designs, multiple category tags.
- Select **Custom Flag**: four recognizable country flag layouts plus brand/event flag designs. Filter **Country Flags** — four cards should remain.
- Select **Yard Sign**: six sign layouts, including **For Sale** and **Reserved Parking**.
- Open two different templates under the same product: the canvas artwork, not only the title, must change.
- In the editor select a textbox; Copy → Paste (offset copy), Duplicate (another copy), Cut (removes copy), Undo/Redo; test Cmd/Ctrl+C/V/D/X and Delete. Change opacity, rotate, flip, lock/unlock.
- Save a modified design and verify cart thumbnail and server price stay correct. Repeat at least one completed order to ensure checkout regressions have not been introduced.

## Implementation notes and limits

- Canvas engine: **Fabric.js 5.3.1** already loaded by the plugin; the clone callback approach follows its v5 APIs.
- The separate JSON sources are in `assets/seeds/*.json`; bundled thumbnails are in `assets/thumbs/*.png`. The manifest is `assets/seed-presets.json`. Run `tools/generate_seed_assets.py` with Python + Pillow to regenerate assets (not required at runtime).
- Four examples use country flags with simple shapes. If used for actual manufactured products, validate official dimensions, colors and flag-use requirements. The **3 × 2 ft** preset matches the initial 2:3 aspect ratio. Arbitrarily changing dimensions may distort a national flag; production software should preserve or warn about required proportions.
- No original v0.1.3 customer designs, products, WooCommerce orders or saved pricing settings are modified by this update.
- Still incomplete from the assessment: full clipart library, QR, 10+ fonts, extra text formatting, printable physical-size exports at 150+ DPI, originals archive, per-side thumbnails, mobile usability QA and final submission artifacts. Do not claim these are finished.
