# AGAS Design Studio v0.1.14 — Category- and physical-format-aware mockups

Builds **on v0.1.13** and changes mockup presentation only. The WooCommerce cart, checkout, pricing, order IDs, saved Fabric.js JSON, admin printer and export algorithms are untouched.

## Problem addressed

Previously all banners used one storefront banner, all flags used one *feather* flag, and all signs used a lawn-stake sign regardless of the template category. This made national flags, reserved parking signs and event banners physically misleading.

## New behavior

Physical profiles are selected using **product + template category + template name + optional admin override**, with an appropriate same-product fallback when a category is unfamiliar.

| Product | Profile | Examples |
|---|---|---|
| Outdoor Vinyl Banner | `banner-storefront` | Grand Opening, Flash Sale, Now Hiring, Coming Soon |
| Outdoor Vinyl Banner | `banner-event` | Music Festival, Community Fundraiser |
| Custom Flag | `flag-rectangular` | France, Japan, Italy, Ukraine (regular landscape flags on a pole) |
| Custom Flag | `flag-feather` | Company Brand, Event / promotional flag |
| Yard Sign | `sign-lawn` | Open House, For Sale, Sold, Welcome Home |
| Yard Sign | `sign-parking` | Reserved Parking and other directional/parking signage |
| Yard Sign | `sign-event` | Event Entrance and other event directional signs |

Four new locally bundled photographic composite backdrops were added for event banners, rectangular flags, parking signs and event signs. The existing three photo scenes remain available. The rectangular country-flag surface has a landscape aspect ratio approximating the 3:2 template to avoid stretching emblems such as Japan's red circle.

## Admin and data model

- Existing seeded templates automatically map from their taxonomy terms and names; **no database migration, post ID changes, or re-seeding**.
- `assets/seed-presets.json` also declares `mockup_profile` per starter template for clean new installs.
- Admin template editing (the same designer) includes **Product mockup format** with `Automatic` and the compatible physical formats for the current product. The explicit choice is saved as sanitized `_agas_mockup_profile` post meta. It is copied when duplicating templates.
- The override is validated per product type, so an invalid or wrong-product profile falls back safely. A template assigned to several products is rendered with the compatible physical profile for the product currently being viewed.
- The mockup profile is **presentation metadata**, not part of the Fabric.js print canvas JSON and not used by the print export pipeline.

## Files

- `agas-design-studio.php` — template profile metadata, dynamic gallery/editor attributes, admin selector, validated REST persistence.
- `assets/realistic-mockups.js` — seven profile-specific mesh/quad placements and shared routing; async image changes protected against stale responses.
- `assets/gallery.js` — template selection changes its photographic scene and physical-format label.
- `assets/studio.js` — editor dual Preview selects scene from the template; admin preview follows current category/override; save template includes override.
- `assets/seed-presets.json` — mapping for 18 starters.
- `assets/mockups/photos/` — four additional locally generated/adapted scene images.
- `assets/studio.css` — format labels and correct fallback outline for rectangular flags/metal signs.
- `tools/test_mockup_profiles.cjs` — routing and asset checks.
- `tools/test_template_admin.php` and `tools/test_editor_features.cjs` — updated regression checks.

## Browser QA

1. **Custom Flag → Country Flags → Japan**: rectangular pole flag, red circle not stretched. **Business → Company Brand**: feather flag.
2. **Outdoor Vinyl Banner → Events → Music Festival**: event/walkway banner. **Sale → Flash Sale**: storefront banner.
3. **Yard Sign → Real Estate → For Sale**: stakes on lawn. **Directions → Reserved Parking**: metal-post parking context. **Events → Event Entrance**: festive directional sign.
4. In the editor, change text and click Preview. The correct physical scene and independent flat artwork must both update. Test both sides.
5. In WP admin, create/edit a template; choose the physical format explicitly, save Draft/Published, and confirm it appears in its proper gallery without overwriting artwork.
6. Verify existing saved designs, customer cart, second-item pricing and actual print-export files are unchanged.

## Limits

- Product mockups are **illustrative previews** made from photographic base scenes and dynamically composited art, not a production photograph, guaranteed fabric simulation, or customer print proof.
- Event/banner and directional/parking backdrops are **photo-derived demo composites** rather than real photographed installations; they can be replaced later by bespoke product photography while keeping the profile logic.
- Aspect and perspective are approximate. Flat artwork and print exports remain the authoritative representations.
