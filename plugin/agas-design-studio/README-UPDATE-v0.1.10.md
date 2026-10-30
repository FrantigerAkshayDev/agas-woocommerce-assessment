# AGAS Design Studio v0.1.10 - Replace or add separately

## Customer experience

When a customer opens **Edit design** from the cart and presses **Save** or **Continue to Cart**, a choice dialog appears:

- **Replace existing item**: update the original design ID and cart line; no duplicate product is created.
- **Add as a separate item**: create an entirely new saved design with a new ID/token, preserving the original cart line, artwork and price. This also works for two designs of the same product.
- **Cancel**: return to the editor without saving anything.

If they choose **Save as a separate design** (without continuing to cart), the editor now edits the independent copy; clicking Continue later adds that saved copy as a new item. The URL changes to the new design to prevent accidental overwriting of the original.

New designs opened from the gallery still proceed normally without an additional prompt.

## Implementation

- Explicit PHP cart action modes: `replace`, `add`, and backward-compatible `auto`.
- Replacement verifies the original cart-line key, design ID, product and token.
- Add-as-new refuses to use a saved design ID already present in the cart. The editor clones the design on a separate REST save before cart submission.
- WooCommerce continues to calculate pricing from the server-side saved design options.
- Existing cart sessions, orders, saved designs and template records are unchanged by this upgrade.

## Acceptance checks

1. Add an original single-sided Yard Sign ($24 for 3 x 2 ft).
2. Open **Edit design**; enable Back; click Continue; choose **Add as a separate cart item**.
3. Cart now has two independent Yard Signs, original $24 and new $36 (subtotal $60). The original canvas/text remain unchanged.
4. Re-edit the new design, change text, choose **Replace existing cart item**. Cart remains at two lines; new one updates.
5. Return to the original design and choose Replace. It updates only that original cart line.
6. Open a fresh template from the gallery; Continue does not prompt and adds a distinct cart line.
7. Press Save from a cart edit and choose **Save as a separate design**. Original cart item must remain unchanged; Continue adds the saved copy later.

Run `php tools/test_cart_regression.php` and `php tools/test_cart_choice.php` for isolated PHP tests. Verify the actual browser flow before submission.
