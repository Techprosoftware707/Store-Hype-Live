# ALWAYS FINAL Social Proof

A production-ready WooCommerce plugin by **ALWAYS FINAL** that shows tasteful, real-time
social-proof notifications built **exclusively from genuine store activity** — with a
server-side Country Targeting System and privacy-first data handling.

> "Someone in Ontario recently purchased BPC-157" · "BPC-157 is currently on sale — Save 25%"

- **Plugin source:** [`always-final-social-proof/`](always-final-social-proof/)
- **Installable ZIP:** [`dist/always-final-social-proof-1.0.0.zip`](dist/) (upload via *Plugins → Add New → Upload Plugin*)
- **End-user documentation:** [`always-final-social-proof/readme.txt`](always-final-social-proof/readme.txt)

## Non-negotiable rules the code enforces

| Rule | Where it is enforced |
|---|---|
| Purchases come only from real orders with qualifying statuses; pending/failed/cancelled/refunded/draft/trash can never qualify | `includes/class-settings.php` (`BLOCKED_STATUSES`), `includes/class-orders.php` |
| Country filter enforced server-side, in the query **and** per order **and** again at serve time | `Country::allowed_countries()`, `Orders::collect()`, `Notifications::queue()` |
| No fallback to another country — an empty result stays empty | `Country::allowed_countries()` has no fallback path |
| Never invent locations, discounts, popularity or counts | `Country::public_location()`, `Products::sale_info()`, `Products::popular_from_counts()`, `Templates::render()` |
| No customer PII leaves the server; order IDs are HMAC-hashed | `Orders::collect()` (field minimisation), `Rest_Api::public_fields()` (output whitelist) |
| Orders are never queried on page load | `includes/class-cache.php` (cron rebuild, atomic lock, stale-while-rebuild) |

## Architecture

```
always-final-social-proof/
├── always-final-social-proof.php   Bootstrap, headers, HPOS/blocks compatibility
├── uninstall.php                   Honors the admin's delete choices; never touches WooCommerce data
├── readme.txt                      WordPress-format documentation
├── includes/
│   ├── class-plugin.php            Hook registration, WooCommerce detection, cache invalidation
│   ├── class-settings.php          Schema, defaults, Settings API sanitization
│   ├── class-security.php          Capability, text/place-name sanitizers, opaque IDs, URL patterns
│   ├── class-logger.php            Optional PII-scrubbed debug logging (WooCommerce logger)
│   ├── class-country.php           WooCommerce country/region data, normalization, scope resolver
│   ├── class-orders.php            wc_get_orders() → anonymised purchase records
│   ├── class-products.php          Eligibility, real sale maths (simple/variable), popularity
│   ├── class-cache.php             Versioned transients, lock, WP-Cron refresh
│   ├── class-templates.php         Token validation and eligibility-aware rendering
│   ├── class-notifications.php     Queue engine (priority/weighted), previews
│   ├── class-rest-api.php          Public notifications + analytics endpoints
│   ├── class-analytics.php         Anonymous daily counters
│   ├── class-targeting.php         Page context + display/exclusion rules
│   ├── class-frontend.php          Deferred script + config (CSS lazy-loaded)
│   ├── class-branding.php          White-label helpers
│   └── class-installer.php         Activation, table, cron, upgrades
├── admin/                          WooCommerce → ALWAYS FINAL Social Proof (11 tabs, live previews)
├── public/                         Vanilla JS toast engine + CSS
├── assets/images/                  ALWAYS FINAL logo
└── languages/                      always-final-social-proof.pot
```

## Requirements

WordPress 6.2+, WooCommerce 7.6+ (tested to 11.1, HPOS and legacy storage), PHP 7.4+.

## Building the ZIP

```bash
bin/build.sh
```

Lints all PHP, minifies `public/js/notifications.js` (via `npx terser` when available),
regenerates the POT (via `wp i18n make-pot` when available) and writes
`dist/always-final-social-proof-<version>.zip`.
