# WP Live Hype

A polished, privacy-safe live activity layer for WooCommerce, developed by **ALWAYS FINAL**.

- **Plugin source:** [`wp-live-hype/`](wp-live-hype/)
- **Installable ZIP:** [`dist/`](dist/) — upload via *Plugins → Add New → Upload Plugin*
- **User documentation:** [`wp-live-hype/readme.txt`](wp-live-hype/readme.txt)

## Activity modes

| Mode | What visitors see |
|---|---|
| **Synthetic** (default) | Weighted promotional rotation from the catalogue — featured products, spotlights, shipping-region messages, genuine sales. No activity claims, no invented times. Works with zero orders. |
| **Hybrid** | Real activity (from orders / aggregate data) leads; the synthetic rotation fills quiet periods. |
| **Aggregate** | Only activity backed by real WooCommerce data. |

Activity wording is reserved for real data; customer and order information never leaves the server.

## Architecture highlights

```
wp-live-hype/
├── wp-live-hype.php              Bootstrap, headers, HPOS/blocks compatibility
├── includes/
│   ├── class-synthetic-engine.php  Seeded per-visitor engine, weighting, anti-repetition, mode merge
│   ├── class-locations.php         Country-aware regions/cities with weighting
│   ├── data/cities.php             Bundled city lists (CA, US, GB, AU, NZ)
│   ├── class-notifications.php     Real-data queue (Hybrid/Aggregate) and item builders
│   ├── class-orders.php            HPOS-safe, country-filtered order aggregation
│   ├── class-products.php          Eligibility, real sale maths, popularity
│   ├── class-cache.php             Cached dataset + product pool, cron refresh, lock
│   ├── class-rest-api.php          GET /activity, POST /events
│   └── …                           settings, templates, analytics, targeting, frontend, branding
├── admin/                        WooCommerce → WP Live Hype (14 tabs, live previews)
├── public/                       Vanilla JS activity layer + CSS
└── assets/audio/                 Four bundled notification sounds
```

## Build

```bash
bin/build.sh   # lint, minify JS, regenerate POT (when wp-cli is available), write dist/wp-live-hype-<version>.zip
```
