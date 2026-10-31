# AGAS Design Studio v0.1.19 — Beginner Editor Tour

## Purpose

Introduces first-time customers to the editor in a dismissible, nine-step,
Canva-style spotlight walkthrough. The existing editor, Fabric canvas, saved
artwork JSON, cart, pricing rules, export and child theme are unchanged.

## User experience

- Starts automatically **once per browser** on the first fresh customer editor
  session. Does not automatically interrupt a returning saved-design edit, an
  admin template editing session, or a read-only order preview.
- Each step highlights the real editor feature and explains what it does:
  1. Welcome and top actions
  2. Browse templates in the left sidebar
  3. Add text, graphics, uploads, backgrounds, QR and layers
  4. Work on the canvas
  5. Contextual toolbar for selected objects
  6. Size, units, finishing and live price in Product Setup
  7. Add Back and Front/Back side previews
  8. Save, undo/redo and dual Preview
  9. Continue to Cart
- On phones and tablets the tour opens the relevant sidebar or settings drawer
  for each stop. The previous drawer and selected-tab state is restored on exit.
- `Next`, `Back`, `Skip tour`, `Finish tour`, Escape and keyboard left/right
  arrows are supported. The dialog uses `aria-modal`, focus wrapping, labelled
  controls and reduced-motion styling.
- **Replay:** click `Need Help?` in the editor footer, then `Start guided tour`.
- After Skip or Finish, only the Boolean `agas_design_studio_tour_v1_seen=1`
  is stored in **browser localStorage**. No personal data or telemetry is sent.
  Visitors can replay at any time; a different device/browser sees its own tour.

## Technical changes

- New `assets/editor-tour.js` enqueued **after** `agas-studio` on the editor page
  only, not the storefront, cart, checkout, or gallery.
- `agas-design-studio.php`: nine-step tour overlay markup in the editor,
  `Start guided tour` button in existing Help dialog, version bump.
- `assets/studio.css`: scoped spotlight, progress, accessible card, responsive
  tooltip placement and reduced-motion support.
- `assets/studio.js`: signals successful editor initialization through
  `window.AGASEditorReady` and the `agas:editor-ready` event.
- New `tools/test_editor_tour.cjs`: tests DOM and script contracts. Existing test
  scripts updated to expect v0.1.19 instead of v0.1.18.

## Validation

1. PHP lint and JavaScript syntax checks.
2. All existing editor, gallery, mockup, template sidebar, template admin,
   cart, pricing and footer regression tests.
3. Browser fixture QA at desktop 1440x900, tablet 1024x768 / 768x1024, phone
   390x844 / 320x640: full tour, spotlight alignment, Back, Finish, replay,
   Escape, responsive card, visible Continue button.

**Limitations:** Browser fixtures and automated tests do not replace a final
real-world acceptance test on the user's localhost WordPress site. The tour is
UI guidance only; it does not demonstrate actual mouse gestures or save designs.
