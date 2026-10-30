# AGAS Design Studio v0.1.2 – REST URL hotfix

Fixes JS REST endpoint construction for WordPress **pretty** and **plain** permalink structures. Previously the pricing call appended `price?width=...` directly to the string returned by `rest_url(...)`, leading to malformed queries on plain-permalink setups. The same issue affected design-saving POST routes.

Changes:
- Use URL and URLSearchParams to append REST paths and query parameters safely.
- Applies to price GET and save-design/save-template POST.
- Clear a stale `No route was found ...` banner after a successful price response.
- Increment plugin and asset version to 0.1.2 to bypass browser caches.

This patch does **not** finish the assessment's remaining features or prove the complete cart/checkout flow. Verify the backend routes independently and test saving and checkout.

## Read-only backend test

```bash
curl -sS -G 'http://127.0.0.1:8099/' \
  --data-urlencode 'rest_route=/agas-design/v1/price' \
  --data-urlencode 'width=3' \
  --data-urlencode 'height=2' \
  --data-urlencode 'unit=ft' \
  --data-urlencode 'double_sided=0' \
  --data-urlencode 'grommets=0' \
  --data-urlencode 'pole_pocket=0'
```

Expected JSON includes `"price":24` if default pricing rules remain unchanged. If `rest_no_route` persists even with this direct query, inspect WordPress plugin activation and REST route registration rather than assuming this front-end patch resolves the backend.
