# AGAS Design Studio v0.1.9 - Text editing and cart design links

This release is based on v0.1.8 and preserves WooCommerce prices, saved designs and cart-item behavior.

## Text editor

- Select text once to open the left-side Edit selected text field.
- Click a text-layer row if selecting the element on the canvas is difficult.
- Edit text directly in the textarea, or click Edit on canvas / press Enter to start inline editing.
- Existing Fabric double-click functionality remains available.
- Lock status and read-only mode disable editing.

## Cart editing

- The Cart block now shows a top panel listing each customized product and an Edit design link.
- The regular product-name link stays unchanged.
- The editor loads the existing saved design using its per-design access token and offers a Back to cart link.
- Saving and continuing to cart updates the existing line rather than adding a new copy, using the existing v0.1.8 cart handling.
- Custom design links are emitted only for items whose cart-session access token matches the saved design token.

## Notes

- WooCommerce does not officially support adding custom per-line buttons within the Cart block. The cart-wide panel is used deliberately instead of fragile DOM manipulation.
- This version has PHP and JS syntax checks and static link tests, but real WordPress cart and browser flow must be verified by the user.
