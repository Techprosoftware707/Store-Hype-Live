=== WP Live Hype ===
Contributors: alwaysfinal
Tags: woocommerce, live activity, sales notification, product spotlight, social proof
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.6
WC tested up to: 11.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A polished, privacy-safe live activity layer for WooCommerce. Developed by ALWAYS FINAL.

== Description ==

**WP Live Hype** makes a store feel alive: a continuous, gently-timed stream of beautiful notification cards — product spotlights, featured products, shipping-region messages, genuine sales and (when your data supports it) real store activity — with optional subtle sounds.

= Activity modes =

* **Synthetic** — a weighted promotional rotation built from your catalogue: "Featured — Retatrutide", "Explore BPC-157", "Ships across Ontario", and genuine WooCommerce sales. Works on a brand-new store with zero orders. Synthetic events never claim that someone bought or viewed something and carry no invented times.
* **Hybrid (default)** — real store activity leads ("Just bought! A customer from Ontario bought 2 × BPC-157", "37 sold in the last 7 days", with real relative times); on a store without orders it behaves like Synthetic; the synthetic rotation fills the quiet periods in between.
* **Aggregate** — only activity backed by real WooCommerce data.

Activity wording ("Just bought!", "sold", "2 min ago") is reserved for events backed by real store data, so the layer stays lively without misleading shoppers.

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

* "Just bought!" notices: real product, real quantity, real location and time from qualifying orders. Units-sold notices ("37 sold in the last 7 days") use real order totals.
* Purchases only from real orders with qualifying statuses (Processing, Completed by default); pending, failed, cancelled, refunded, draft and trashed orders never qualify.
* Server-side country targeting with no fallback to other countries.
* Real sale pricing for simple and variable products; discounts rounded down, never invented.
* Customer names, emails, phones, addresses, postcodes, order numbers/IDs and payment data never leave the server.

= Design & sound =

* Premium card: product thumbnail, strong product name, location, subtle indicators, close button; light, dark, auto or custom theme.
* Slide + fade (default), slide, fade, scale or none; `prefers-reduced-motion` respected.
* Fixed overlay animated with transform/opacity only — no layout shift. CSS is lazy-loaded.
* Four bundled sounds (Soft Chime, Modern Notification, Subtle Pop, Clean Alert) with volume and separate desktop/mobile switches. Audio follows browser autoplay rules: it only plays after the visitor has interacted with the page; otherwise notifications continue silently.

= Conversion assistant =

WP Live Hype also helps visitors take their next step, using only genuine store facts:

* **Decision engine:** each visitor's stage (new, engaged, product explorer, cart builder, checkout) is worked out in their own browser from page views, product visits, scrolling, time on page, clicks and cart status. Context-relevant messages come first, ordered by your **primary goal** (product views, add to cart, checkout or purchase).
* **Product call to action:** after a visitor shows interest in a product, an "Add to cart" button (simple products) or "Choose options" (scrolls to the product form). Nothing is added to the cart unless the visitor clicks.
* **Recommendations** from your up-sells / cross-sells and WooCommerce related products — no claims about why products belong together.
* **Cart reminder, checkout prompt and free-shipping progress** ("You are $X away from free shipping") from the visitor's real cart (WooCommerce Store API) and your real free-shipping rule — the minimum amount is read from WooCommerce, never hard-coded, and calculated exactly as WooCommerce does.
* **Smart timing and suppression:** presets (Soft, Balanced, Aggressive or Custom); waits for interest on product pages; more room after a click; a quiet period after add to cart; one final low-intensity message after 45 seconds of inactivity, then nothing; silent on checkout; free-shipping progress only on the cart page; stops after repeated dismissals; calmer on repeatedly viewed products; never while typing.
* **Placement:** cards never cover add-to-cart, cart, checkout or payment controls — they move to the opposite edge or wait. Touch-friendly 44px buttons and safe-area support on mobile.
* **Calls to action:** configurable text and appearance; buttons are separate from the message link and tracked.
* **A/B testing** of button text, message copy, position, animation, frequency, sound or image, with a two-proportion significance test.
* **Conversion analytics:** funnel (sessions → product views → add to cart → cart → checkout → orders), direct notification metrics, "after a click" (attributed) metrics, mobile vs desktop and a Conversion Health panel built only from measured counters. Attributed results are presented as associations, never as proof of cause.

Never invented: discounts, coupons, deadlines, countdowns, stock shortages, viewer counts or customers.

= Admin =

WooCommerce → WP Live Hype: Dashboard, General, Live Hype, Conversion, A/B testing, Weighting, Country, Notifications (templates & tokens), Display, Sound, Frequency, Products, Data, Analytics, Advanced, Branding. Live desktop/mobile previews are labelled "SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY".

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

= Does the conversion assistant use cookies? =

Funnel counters use no cookies. If attribution is enabled (Conversion tab), clicking a message sets a first-party cookie `wplh_attr` containing a random ID, the A/B variant letter, a mobile/desktop flag and a timestamp, which expires after the attribution window (30 minutes to 24 hours). An order placed within the window is flagged with only the variant letter. Turn attribution off if your cookie policy requires consent for it.

= Where does the free-shipping amount come from? =

From the WooCommerce Free Shipping method (minimum order amount) in the shipping zone of your target country. If none exists, free-shipping messages stay off.

= Does it work with HPOS and the Cart/Checkout blocks? =

Yes. Compatibility is declared and all order access uses `wc_get_orders()`.

== Developers ==

`GET /wp-json/wp-live-hype/v1/activity?ctx=product&pid=123&tid=0&seed=42`

Example synthetic event:

`{"id":"syn_6ade3151827cd9f2","type":"featured","product":"BPC-157","product_id":123,"url":"https://…","image":"https://…","message":"Featured — BPC-157","label":"Featured","synthetic":true}`

On product pages the response also includes `context` (`product`: the product call to action, `related`: up to three recommendations).

`POST /wp-json/wp-live-hype/v1/events` — anonymous analytics counters (`events`) and funnel counters (`funnel`: event name, A/B variant letter, mobile/desktop). Purchases are never accepted from the browser; they are recorded server-side when an attributed order is created.

Filters: `wplh_capability`, `wplh_should_display`, `wplh_order_query_args`, `wplh_product_is_featurable`, `wplh_notification_queue`, `wplh_region_name`, `wplh_analytics_rate_limit`. Lock branding with `define( 'WPLH_LOCK_BRANDING', true );`.

== Uninstall ==

Deleting the plugin removes what you selected on the Advanced tab: settings, cached data and/or analytics (including the conversion funnel table). WooCommerce products, customers, orders and core data are never touched.

== Changelog ==

= 1.3.0 =
* Hybrid is now the default mode for new installs, so real activity leads.
* Cookie consent: the attribution cookie respects the WP Consent API (Complianz, CookieYes and others) and is described to consent plugins.
* Suggested privacy policy text in Settings → Privacy.
* Right-to-left (RTL) layout for notifications.
* French translation (fr_FR, fr_CA).
* Clearer wording for the popularity threshold and the analytics counters.

= 1.2.0 =
* "Just bought!" purchase notices built from real orders: product, real quantity (when 2 or more), location and time.
* Units-sold notices from real order totals, e.g. "37 sold in the last 7 days: Retatrutide" (lifetime mode uses WooCommerce's own sales counter). A number is only shown when the order scan for the period is complete.
* Sales-count and sale notices are interleaved with purchases so they stay visible on busy stores; popular/units-sold notices are on by default for new installs.

= 1.1.0 =
* New conversion assistant: visitor-stage decision engine, product calls to action, recommendations, cart reminder, checkout prompt and genuine free-shipping progress.
* Smart timing, inactivity handling, suppression and collision-free placement on desktop and mobile.
* Configurable calls to action, conversion presets and primary goal.
* A/B testing with significance testing.
* Conversion funnel, anonymous attribution (HPOS and legacy storage, classic and block checkout) and Conversion Health.

= 1.0.0 =
* Initial release of WP Live Hype by ALWAYS FINAL.
