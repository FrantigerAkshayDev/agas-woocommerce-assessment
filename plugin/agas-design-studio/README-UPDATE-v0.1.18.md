# AGAS Design Studio v0.1.18 — mobile editor viewport fix

Fixes a real-world issue visible on a logged-in administrator's phone: WordPress
adds a top toolbar and `html { margin-top:46px !important }` but the standalone
editor also asks for `100dvh`. That double-counts the toolbar offset and can
push the footer's Continue button below the visible screen.

- Disable the WordPress admin toolbar **only on the plugin's editor page**, even
  when the user is logged in. Do not change the admin dashboard, gallery or
  storefront toolbar behavior.
- Ensure `html` has no residual admin-bar top offset on the editor page, with
  an editor-only CSS fallback for admin-bar markup inserted by other plugins.
- Preserve the existing two-row responsive footer; allow the canvas area to
  shrink and scroll rather than hiding Continue under the viewport edge.
- No changes to cart, pricing, templates, saved designs, print exports or
  WooCommerce orders.

## Acceptance

Verify in Chrome while logged in as administrator and in guest/incognito mode.
At 320, 390, 430, 658, 768 and 1024px widths, confirm the top WordPress toolbar
is not visible and the entire Continue to Cart button is clickable without
scrolling the browser page. On other WordPress pages the toolbar remains visible
for administrators. Check mobile orientation changes and on-screen keyboard.
