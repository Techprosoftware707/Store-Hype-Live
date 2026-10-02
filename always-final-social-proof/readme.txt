=== ALWAYS FINAL Social Proof ===
Contributors: alwaysfinal
Tags: woocommerce, social proof, sales notification, recent sales, fomo
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.6
WC tested up to: 11.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tasteful, real-time social-proof notifications built exclusively from genuine WooCommerce activity — with server-side country targeting and privacy-first data handling.

== Description ==

**ALWAYS FINAL Social Proof** shows elegant, non-intrusive notifications such as:

* "Someone in Ontario recently purchased BPC-157"
* "A customer in Toronto recently ordered 5-Amino-1MQ"
* "BPC-157 is currently on sale — Save 25%"
* "Retatrutide has been popular recently"

Every notification is backed by real store data. Nothing is ever fabricated.

= Honest by design =

* **Purchases** come only from real WooCommerce orders with qualifying statuses (Processing and Completed by default). Pending payment, failed, cancelled, refunded, draft and trashed orders can never qualify.
* **Sales** appear only for products with a genuine, currently active WooCommerce sale price. Discounts are calculated from real prices and rounded *down*. Variable products show honest ranges ("from $40.00", "up to 30%").
* **Popularity** is claimed only when real orders meet a configurable threshold. No visitor counters, no scarcity, no invented numbers.
* **Locations** are never invented. Missing data falls back gracefully: City + region → Region → Country → No location.
* Cancelled or refunded orders are removed from notifications promptly.

= Country Targeting System =

The same plugin can be deployed in any country — nothing is hard-coded.

* **Enable Country Filtering** and pick a **Target Country** from WooCommerce's own country list (ISO codes such as CA, US, GB, AU).
* Only qualifying orders whose billing/shipping country matches are used. The rule is enforced **on the server**, in the order query *and* re-checked per order and again when notifications are served.
* **No fallback.** If the selected country has no qualifying orders, purchase notifications stay inactive. Orders from another country are never substituted.
* When filtering is OFF, choose explicitly: all valid store orders, only the store's base country, only specific countries, or keep purchase notifications inactive until a country is configured.
* **Location display:** country only, province/state/region, city, or city + region. Region codes are normalized to readable names (ON → Ontario, BC → British Columbia, QC → Quebec, YT → Yukon…).
* **Country preview** shows how a notification would read for any country, clearly labelled *PREVIEW — NOT REAL CUSTOMER ACTIVITY*.

= Privacy =

The browser only ever receives what is needed to render a notification: product name, link and image, the rendered message, a coarse location (only to the configured level) and an order time rounded down to 5 minutes. It never receives customer names, email addresses, phone numbers, street addresses, postal/ZIP codes, order numbers or IDs, payment information or order metadata. Order IDs are replaced by a keyed, non-reversible hash even inside the server-side cache. Free-text city/region values that look like addresses, postcodes or phone numbers are discarded.

= Fast on modest servers =

* Orders are **never** queried on page load. A sanitized dataset is rebuilt in the background (WP-Cron, 1–30 minute interval) with an atomic lock to prevent stampedes, and served from transients — Redis/Memcached compatible.
* Only fields that are needed are read; scans are capped and use `wc_get_orders()` (HPOS and legacy storage).
* One small deferred, dependency-free script loads only on targeted pages. The stylesheet is injected just before the first notification, so it never blocks rendering (no LCP impact). Toasts are fixed overlays animated with opacity/transform only (no layout shift).
* The public REST response is identical for every visitor with the same page context, sends `Cache-Control: public`, and is cached in the visitor's session.

= Features =

* Four notification types: recent product purchase (prioritized on product and category pages), recent purchase, active sale, popular product — strict priority or weighted mix.
* Editable multi-line templates with tokens: `{product}` `{location}` `{country}` `{region}` `{province}` `{state}` `{city}` `{time_ago}` `{sale_price}` `{regular_price}` `{discount_percent}` `{period}`. Templates are validated on save; a template is only used when all its tokens have real data.
* Premium, responsive design: light, dark, automatic or custom colors; desktop and mobile positions; slide, fade, scale or no animation; `prefers-reduced-motion` respected.
* Frequency controls: first delay, duration, minimum interval, per-session and per-page limits, dismissal behaviour, pause on hover/focus; no repeats within a session.
* Targeting: entire site or selected page types; exclusions for account, login, checkout, cart, specific products, categories and URLs (with wildcards).
* Anonymous, local analytics: shown, clicks, CTR, dismissals, by type and product. No IPs, cookies or personal data; configurable retention.
* Live desktop/mobile admin preview, plus an on-site preview visible only to store managers.
* Branding & white-label controls; subtle "Developed by ALWAYS FINAL" admin credit.
* Accessible: polite live region, keyboard support (Esc to dismiss), 28px close target, focus pauses the timer, screen-reader text for prices.
* Debug logging to WooCommerce → Status → Logs (customer data is never logged).
* Fully translatable (text domain `always-final-social-proof`).

== Installation ==

1. Make sure WooCommerce 7.6 or newer is active.
2. Upload the `always-final-social-proof` folder to `/wp-content/plugins/`, or upload the ZIP via Plugins → Add New → Upload Plugin.
3. Activate the plugin.
4. Go to **WooCommerce → ALWAYS FINAL Social Proof** and review the **Country** tab. The target country defaults to your store's base country.

If WooCommerce is not active the plugin stays dormant and shows: "ALWAYS FINAL Social Proof requires WooCommerce."

== Frequently Asked Questions ==

= Can notifications ever show fake activity? =

No. Purchase, sale and popularity notifications are built only from real WooCommerce data. When there is no qualifying data, nothing is shown. Admin previews are clearly labelled "PREVIEW — NOT REAL CUSTOMER ACTIVITY" and are never shown to customers.

= My target country has no orders yet. What happens? =

Purchase notifications remain inactive until qualifying order activity exists. Orders from other countries are never used as a fallback. Real sale notifications can still appear.

= Which address decides the country? =

Your choice on the Country tab: billing address, shipping address, or shipping address with billing as a fallback for orders without shipping. The same address is used for the displayed location, so it is always inside the permitted country.

= Why are "Lifetime" popular products inactive? =

Lifetime popularity uses WooCommerce's store-wide `total_sales` counter, which cannot be filtered by country. It is only used when no geographic restriction applies. Use a 24-hour, 7-day or 30-day window instead — those respect country targeting.

= Does it work with HPOS and the Cart/Checkout blocks? =

Yes. All order access uses the WooCommerce CRUD API (`wc_get_orders()`), and compatibility with High-Performance Order Storage and the Cart/Checkout blocks is declared.

= Does it work with page caching and CDNs? =

Yes. Page HTML contains no user-specific data. Notifications are fetched from a public, cache-friendly REST endpoint after the page loads.

= How do I lock branding on client sites? =

Add `define( 'AFSP_LOCK_BRANDING', true );` to `wp-config.php` to hide the Branding tab.

== Developers ==

= REST API =

`GET /wp-json/always-final-social-proof/v1/notifications?ctx=product&pid=123&tid=0`

* `ctx` — page context: home, shop, product, category, tag, cart, checkout, account, page, post, other.
* `pid` — product ID being viewed (prioritizes its real purchases).
* `tid` — product category ID being viewed.

Example item:

`{"id":"6ade3151827cd9f2","type":"purchase","product":"BPC-157","product_id":10,"url":"https://…","image":"https://…","message":"Someone in Ontario recently purchased BPC-157","label":"Recent purchase","location":"Ontario","country":"Canada","region":"Ontario","timestamp":"2026-10-02T00:40:00Z","time_ago":"1 hour ago","verified":true}`

`POST /wp-json/always-final-social-proof/v1/events` — anonymous analytics counters, body `{"events":[{"e":"view","t":"sale","p":12}]}`.

= Filters =

* `afsp_capability` — capability required to manage the plugin (default `manage_woocommerce`).
* `afsp_should_display` — whether notifications load on the current page.
* `afsp_order_query_args` — `wc_get_orders()` arguments (the country rule is re-applied regardless).
* `afsp_product_is_featurable` — whether a product may appear in notifications.
* `afsp_notification_queue` — the public queue (never add personal data).
* `afsp_region_name` — readable region name.
* `afsp_analytics_rate_limit` — events accepted per minute site-wide (default 1200).

== Uninstall ==

Deleting the plugin removes what you selected on the Advanced tab: settings, cached data and/or analytics. Scheduled events are always removed. WooCommerce orders, customers, products and product data are never touched.

== Changelog ==

= 1.0.0 =
* Initial release by ALWAYS FINAL.
