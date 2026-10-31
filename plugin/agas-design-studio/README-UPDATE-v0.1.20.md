# AGAS Design Studio v0.1.20 - My Designs in WooCommerce My Account

## New customer-facing section

- Registers WooCommerce account endpoint `my-designs` and inserts **My Designs** after **Orders** in the My Account navigation.
- URL: `/my-account/my-designs/` on sites using standard pretty permalinks (the WooCommerce helper generates the URL dynamically).
- The dashboard has a brief My Designs shortcut and saved-design count.
- The dedicated tab displays front-artwork cards with product, selected template name, size, front/back status, modified date and an **Edit design** link.
- A click-to-enlarge image preview opens in a keyboard-accessible native dialog; designs without preview images show a placeholder.
- Product and template-name search, lookup by design number, product filter, recent/oldest sort and server-side pagination (9 items per page). Saving an edited design also refreshes its modified timestamp so recently updated sorting is accurate.
- Uses the existing `agas_design` private posts and metadata. No schema or asset migration, destructive cleanup, new external dependencies or changes to checkout/pricing.
- The former unstyled designs list appended to Account pages has been removed.

## Access and guest behavior

- Only authenticated account-owner posts (`post_author = get_current_user_id()`, `post_status = private`) are queried. An explicit author check also runs before rendering each card.
- The account editor link does not include a guest access token because the owner is already logged in and authorized.
- Designs created anonymously retain their private token links and do not automatically appear in a subsequently signed-in account. No automatic reassignment of guest designs has been implemented.
- Saved design files may be under public uploads URLs as in prior releases. This release does not change the existing preview-file storage security model.
- If a design's original template is no longer available, it still opens from its saved JSON with a blank template parameter. If the associated demo product is no longer available, the card shows a warning instead of a broken edit link.

## Install / update

- Install only over v0.1.19 (check the PHP version constant). Back up plugin source and MariaDB before copying changed files.
- The upgrade registers the account rewrite endpoint and flushes the soft rewrite rules **once**. Repeated page loads do not repeatedly flush.
- If the endpoint URL is 404 after install, open WordPress > Settings > Permalinks and Save Changes once. Do not create a second WordPress page called My Designs.
- `account-designs.css` and `account-designs.js` load only on the WooCommerce Account page.

## Acceptance checklist on the real local store

1. Visit the normal My Account page while signed in. My Designs should appear after Orders in the account nav, and the dashboard shortcut should link to it.
2. Open My Designs and ensure only designs you saved **while logged into that account** appear. Check artwork thumbnails, product names, sizes and front/back badges.
3. Preview a design in the dialog; close with the button or Escape. The preview shows the saved front artwork (not a print-file proof).
4. Filter by Custom Flag, search for Japan or a saved design ID, then reset filters. Check Next/Previous with at least 10 designs.
5. Click Edit design and verify it opens the original saved JSON in the editor; make a change, save, return to My Designs and verify the update is reflected.
6. Sign into a *different* test account; it must not see the first account's design or open that design's account-only link.
7. Open the account on mobile and tablet. Cards should stack, filters remain usable and no content should overflow horizontally.
8. Check that Cart, Checkout, the template gallery, design editor and order print exports still behave normally.

## Isolated tests

`php tools/test_account_designs.php` validates registered endpoint/menu, one-time soft rewrite flush, private owner isolation, search/sort filters, pagination, escaping, and logged-out access. Existing cart/admin/editor tests remain available under `tools/`. Browser fixture screenshots in development are synthetic and are **not** proof of the actual user's authenticated WordPress database/session.
