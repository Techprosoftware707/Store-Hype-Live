=== WP Live Hype ===
Contributors: alwaysfinal
Tags: woocommerce, live activity, sales notification, product spotlight, social proof
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.6
WC tested up to: 11.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A polished, privacy-safe live activity layer for WooCommerce. Developed by ALWAYS FINAL.

== Description ==

**WP Live Hype** makes a store feel alive: a continuous, gently-timed stream of beautiful notification cards — product spotlights, featured products, shipping-region messages, genuine sales and (when your data supports it) real store activity — with optional subtle sounds.

= Activity modes =

* **Synthetic (default)** — a weighted promotional rotation built from your catalogue: "Featured — Retatrutide", "Explore BPC-157", "Ships across Ontario", and genuine WooCommerce sales. Works on a brand-new store with zero orders. Synthetic events never claim that someone bought or viewed something and carry no invented times.
* **Hybrid** — real store activity leads ("Someone in Ontario recently purchased BPC-157", "has been popular recently", with real relative times); the synthetic rotation fills the quiet periods in between.
* **Aggregate** — only activity backed by real WooCommerce data.

Activity wording ("recently purchased", "popular", "2 min ago") is reserved for events backed by real store data, so the layer stays lively without misleading shoppers.

= The engine =

* Weighted product selection: Low / Normal / High / Featured, with a live share-of-rotation bar.
* Region weighting (Off / Low / Normal / High) and per-city enable/weight. Regions come from WooCommerce; cities from a bundled list (Canada, United States, United Kingdom, Australia, New Zealand). Locations never leave the selected country, and cities are never invented.
* Shipping-region messages appear only when WooCommerce is configured to ship to that country.
* Randomized timing between a minimum and maximum gap (e.g. 27s, 41s, 18s, 73s…).
* Per-visitor sequences: each visitor gets a different, stable rotation.
* Anti-repetition: types alternate, recent products are avoided, and a product/location/message fingerprint is not repeated within a configurable cooldown.
* Product relevance: on a product page that product is favoured; on a category page, products in that category.
* Optional aggregate weighting: WooCommerce's store-wide sales counters can influence frequency. Individual orders are never read for the synthetic rotation, and nothing about them is sent to visitors.

= Real data (Hybrid / Aggregate) =

* Purchases only from real orders with qualifying statuses (Processing, Completed by default); pending, failed, cancelled, refunded, draft and trashed orders never qualify.
* Server-side country targeting with no fallback to other countries.
* Real sale pricing for simple and variable products; discounts rounded down, never invented.
* Customer names, emails, phones, addresses, postcodes, order numbers/IDs and payment data never leave the server.

= Design & sound =

* Premium card: product thumbnail, strong product name, location, subtle indicators, close button; light, dark, auto or custom theme.
* Slide + fade (default), slide, fade, scale or none; `prefers-reduced-motion` respected.
* Fixed overlay animated with transform/opacity only — no layout shift. CSS is lazy-loaded.
* Four bundled sounds (Soft Chime, Modern Notification, Subtle Pop, Clean Alert) with volume and separate desktop/mobile switches. Audio follows browser autoplay rules: it only plays after the visitor has interacted with the page; otherwise notifications continue silently.

= Admin =

WooCommerce → WP Live Hype: Dashboard, General, Live Hype, Weighting, Country, Notifications (templates & tokens), Display, Sound, Frequency, Products, Data, Analytics, Advanced, Branding. Live desktop/mobile previews are labelled "SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY".

= Performance & security =

* Product pool, sales and real-data datasets are cached (transients; Redis/Memcached compatible) and rebuilt in the background.
* The public REST endpoint is cache-friendly and returns only sanitized event fields.
* Settings API with nonces, capability checks (`manage_woocommerce`), schema-validated REST input, prepared SQL, escaped output.

== Installation ==

1. Make sure WooCommerce 7.6+ is active.
2. Upload the ZIP via Plugins → Add New → Upload Plugin and activate.
3. Open WooCommerce → WP Live Hype. The country defaults to your store's base country; review the Live Hype and Weighting tabs.

If WooCommerce is not active the plugin stays dormant and shows an admin notice.

== Frequently Asked Questions ==

= Will it work on a new store with no orders? =

Yes. Synthetic mode needs only published products. Activity-style messages appear automatically in Hybrid mode once real orders exist.

= Why doesn't synthetic mode say "Someone in Toronto just bought…"? =

Because that would tell shoppers something that did not happen. Synthetic events use promotional wording; activity claims come only from real data.

= Why is my sound not playing? =

Browsers block audio until the visitor clicks, taps or presses a key on the page. After that, sounds play normally.

= Does it work with HPOS and the Cart/Checkout blocks? =

Yes. Compatibility is declared and all order access uses `wc_get_orders()`.

== Developers ==

`GET /wp-json/wp-live-hype/v1/activity?ctx=product&pid=123&tid=0&seed=42`

Example synthetic event:

`{"id":"syn_6ade3151827cd9f2","type":"featured","product":"BPC-157","product_id":123,"url":"https://…","image":"https://…","message":"Featured — BPC-157","label":"Featured","synthetic":true}`

`POST /wp-json/wp-live-hype/v1/events` — anonymous analytics counters.

Filters: `wplh_capability`, `wplh_should_display`, `wplh_order_query_args`, `wplh_product_is_featurable`, `wplh_notification_queue`, `wplh_region_name`, `wplh_analytics_rate_limit`. Lock branding with `define( 'WPLH_LOCK_BRANDING', true );`.

== Uninstall ==

Deleting the plugin removes what you selected on the Advanced tab: settings, cached data and/or analytics. WooCommerce products, customers, orders and core data are never touched.

== Changelog ==

= 1.0.0 =
* Initial release of WP Live Hype by ALWAYS FINAL.
