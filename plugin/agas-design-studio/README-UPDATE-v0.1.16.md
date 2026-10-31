# AGAS Design Studio v0.1.16 — Editor Workspace & Exclusive Finishing

## Scope
This release reorganizes the existing Fabric.js editor without changing the saved canvas JSON, products, orders, cart session behavior, mockup profile routing, PDF/PNG export, or template storage.

### Insertion rail (left)
- Seven accessible icon tabs: Templates, Text, Elements, Uploads, Background, QR code and Layers.
- Clicking a tab changes the drawer contents; the selected tab is visually highlighted and arrow-key navigable.
- Change Template is located in Templates and retains the existing unsaved-changes confirmation.
- Content creation remains in the left drawer. On tablet/phone, it overlays the canvas and can be dismissed.

### Context toolbar (top of canvas)
- No selection: shows a short guidance label.
- Text selection: edit content, font family/size/color, bold/italic/underline, alignment and spacing.
- Any editable selection: icon-only Copy/Paste/Duplicate/Cut/Delete, layering, rotation, flip, align, lock and opacity tools.
- Commands have accessible names, native tooltips and keyboard support. Undo/Redo use familiar curved arrow icons.
- Save, Preview and Continue retain explicit text because these are primary navigation actions, not ambiguous editing tools.

### Right panel
- Size, dimensions and units remain unchanged.
- Finishing is **None / Grommets / Pole pocket**, implemented using exclusive radio buttons.
- The server rejects requests asking for both grommets and a pole pocket, preventing browser price tampering or two charges.
- **Add Back** is grouped inside Side previews, directly beside front/back thumbnails.
- Existing saved designs that contain both legacy extras must be reopened and saved with one valid selection; do not silently alter previous orders.

### Responsive behavior
- Desktop (1101px+): insertion rail + visible drawer / flexible canvas / existing product setup panel.
- Tablet and phone (1100px or less): rail remains visible, insertion drawer opens on demand, product settings opens from a gear button; the canvas remains the primary view.
- Small phones: compact two-row bottom bar keeps zoom, price, help and Continue accessible.
- Canvas coordinates and saved JSON never depend on viewport scaling.

## Deployment and validation
- Expected previous version: 0.1.15. Apply changed files on top of the existing plugin, with a plugin + database backup.
- PHP lint and Node JS syntax must pass.
- Run `tools/test_editor_layout.cjs`, `tools/test_editor_features.cjs`, `tools/test_cart_regression.php`, `tools/test_template_admin.php` and previous mockup/gallery tests.
- Browser-verify inserting text/images/QR, editing a selected element, side selection, pricing and cart checkout at desktop and phone widths.

## Known boundaries
- This is a custom, Canva-*inspired* workflow, not Canva software or a copied Canva theme.
- On narrow displays, the properties toolbar is horizontally scrollable; it uses native tooltips for icon-only controls.
- This release **does not** yet implement a first-run guided tour, which is a separate requested task.
- Code and mocked browser-layout tests do not replace real WordPress QA for saved designs, uploaded art or the storefront checkout path.
