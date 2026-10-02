<?php
/**
 * Conversion tab: presets, goal, conversion messages, calls to action,
 * suppression, attribution and conversion templates.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_data = Cache::get_dataset();
$wplh_free = $wplh_data['free_shipping'] ?? null;

/**
 * Human summary of a timing preset.
 *
 * @param array $t Timing.
 * @return string
 */
$wplh_summary = static function ( array $t ): string {
	return sprintf(
		/* translators: 1: first delay seconds, 2: min interval seconds, 3: max interval seconds, 4: per page, 5: per session. */
		__( 'First after %1$ds · every %2$d–%3$ds · up to %4$d per page, %5$d per session.', 'wp-live-hype' ),
		$t['first'],
		$t['interval'],
		$t['intervalMax'],
		$t['maxPage'],
		$t['maxSession']
	);
};

Admin::form_start( 'conversion' );
?>
<section class="wplh-card wplh-country-hero">
	<div class="wplh-country-hero__head">
		<span class="dashicons dashicons-performance" aria-hidden="true"></span>
		<div>
			<h2 class="wplh-card__title"><?php esc_html_e( 'Conversion strategy', 'wp-live-hype' ); ?></h2>
			<p class="wplh-card__intro"><?php esc_html_e( 'WP Live Hype adapts to each visitor\'s stage — browsing, exploring a product, building a cart — using signals that stay in their own browser. It shows the next helpful step, then gets out of the way.', 'wp-live-hype' ); ?></p>
		</div>
	</div>
	<?php
	Admin::radios(
		'conversion_preset',
		__( 'Intensity preset', 'wp-live-hype' ),
		array(
			'soft'       => array( __( 'Soft', 'wp-live-hype' ), $wplh_summary( Conversion::PRESETS['soft'] ) ),
			'balanced'   => array( __( 'Balanced (recommended)', 'wp-live-hype' ), $wplh_summary( Conversion::PRESETS['balanced'] ) ),
			'aggressive' => array( __( 'Aggressive', 'wp-live-hype' ), $wplh_summary( Conversion::PRESETS['aggressive'] ) ),
			'custom'     => array( __( 'Custom', 'wp-live-hype' ), __( 'Use the values on the Frequency tab.', 'wp-live-hype' ) ),
		)
	);
	Admin::radios(
		'conversion_goal',
		__( 'Primary goal', 'wp-live-hype' ),
		array(
			'views'    => array( __( 'Product views', 'wp-live-hype' ), __( 'Lead with recommendations and "View product" buttons.', 'wp-live-hype' ) ),
			'cart'     => array( __( 'Add to cart', 'wp-live-hype' ), __( 'Lead with the product being viewed and an "Add to cart" button.', 'wp-live-hype' ) ),
			'checkout' => array( __( 'Checkout', 'wp-live-hype' ), __( 'For visitors with a cart, lead with checkout and free-shipping progress.', 'wp-live-hype' ) ),
			'purchase' => array( __( 'Purchase', 'wp-live-hype' ), __( 'Like Checkout; the dashboard highlights attributed purchases.', 'wp-live-hype' ) ),
		)
	);
	?>
	<div class="wplh-callout wplh-callout--info">
		<span class="dashicons dashicons-shield" aria-hidden="true"></span>
		<p><?php esc_html_e( 'Conversion messages only use genuine store facts: your products and their real prices and sales, WooCommerce related products, your configured free-shipping rule and the visitor\'s own cart. Discounts, coupons, deadlines, stock shortages, viewer counts and customers are never invented. Nothing is shown on the checkout page, and nothing is ever added to the cart without the visitor clicking.', 'wp-live-hype' ); ?></p>
	</div>
</section>
<?php
Admin::card_start( __( 'Conversion messages', 'wp-live-hype' ), __( 'Context-aware messages that are chosen before the regular rotation when they are relevant to the visitor.', 'wp-live-hype' ) );
Admin::toggle( 'conv_product_cta', __( 'Product page call to action', 'wp-live-hype' ), __( 'After the visitor shows interest (scrolling or reading), a message about the product they are viewing with an "Add to cart" button (simple products) or "Choose options" (which scrolls to the product options).', 'wp-live-hype' ) );
Admin::toggle( 'conv_recommend', __( 'Recommendations', 'wp-live-hype' ), __( 'Products from your own up-sells / cross-sells and WooCommerce related products. No claims are made about why products belong together.', 'wp-live-hype' ) );
echo '<div data-show-when="conv_recommend">';
Admin::select(
	'recommend_source',
	__( 'Recommendation source', 'wp-live-hype' ),
	array(
		'wc'       => __( 'Up-sells & cross-sells first, then related products', 'wp-live-hype' ),
		'category' => __( 'Related products only (same categories / tags)', 'wp-live-hype' ),
	)
);
echo '</div>';
Admin::toggle( 'conv_cart', __( 'Cart reminder', 'wp-live-hype' ), __( 'For visitors with items in their cart: their real subtotal and a "View cart" button.', 'wp-live-hype' ) );
Admin::toggle( 'conv_free_shipping', __( 'Free-shipping progress', 'wp-live-hype' ), __( '"You are $X away from free shipping" with a "Continue shopping" button, calculated exactly like WooCommerce.', 'wp-live-hype' ) );
if ( $wplh_free ) {
	echo '<p class="wplh-help wplh-help--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html(
		sprintf(
			/* translators: 1: amount, 2: country. */
			__( 'Detected in WooCommerce: free shipping from %1$s for %2$s.', 'wp-live-hype' ),
			Products::format_price( (float) $wplh_free['min'] ),
			Country::name( (string) $wplh_free['country'] )
		)
	) . '</p>';
} else {
	echo '<p class="wplh-help">' . esc_html__( 'No free-shipping method with a minimum order amount was found for your target country, so free-shipping messages stay off. Configure one under WooCommerce → Settings → Shipping.', 'wp-live-hype' ) . '</p>';
}
Admin::toggle( 'conv_checkout', __( 'Checkout prompt', 'wp-live-hype' ), __( 'For visitors with a cart, when the goal is Checkout or Purchase: "Ready to check out?" with a "Checkout" button (replaces the cart reminder).', 'wp-live-hype' ) );
Admin::toggle( 'conv_nudge', __( 'Inactivity message', 'wp-live-hype' ), __( 'When a visitor has been inactive for 45 seconds: one final, low-intensity message (no sound), then nothing more on that page.', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Calls to action', 'wp-live-hype' ), __( 'Buttons are separate from the message link, touch-friendly on mobile, and only navigate when clicked. Leave a text empty to use the translated default.', 'wp-live-hype' ) );
Admin::toggle( 'cta_enabled', __( 'Show call-to-action buttons', 'wp-live-hype' ) );
echo '<div data-show-when="cta_enabled">';
Admin::select(
	'cta_style',
	__( 'Appearance', 'wp-live-hype' ),
	array(
		'button' => __( 'Button (accent colour)', 'wp-live-hype' ),
		'link'   => __( 'Text link with arrow', 'wp-live-hype' ),
	)
);
$wplh_cta_labels   = array(
	'view'     => __( 'View product', 'wp-live-hype' ),
	'explore'  => __( 'Explore (spotlights)', 'wp-live-hype' ),
	'offer'    => __( 'Offer (genuine sales)', 'wp-live-hype' ),
	'shop'     => __( 'Shop (shipping & inactivity messages)', 'wp-live-hype' ),
	'add'      => __( 'Add to cart', 'wp-live-hype' ),
	'options'  => __( 'Choose options', 'wp-live-hype' ),
	'cart'     => __( 'View cart', 'wp-live-hype' ),
	'checkout' => __( 'Checkout', 'wp-live-hype' ),
	'continue' => __( 'Continue shopping', 'wp-live-hype' ),
);
$wplh_cta_defaults = Conversion::default_cta_texts();
echo '<div class="wplh-grid-2">';
foreach ( $wplh_cta_labels as $wplh_key => $wplh_label ) {
	Admin::text( 'cta_' . $wplh_key, $wplh_label, '', $wplh_cta_defaults[ $wplh_key ] );
}
echo '</div></div>';
Admin::card_end();

Admin::card_start( __( 'Smart suppression', 'wp-live-hype' ), __( 'Always on: nothing on checkout; on the cart page only free-shipping progress; a 20-second pause after an add to cart; never while the visitor is typing; never over add-to-cart, cart, checkout or payment controls (the card moves to the opposite edge or waits).', 'wp-live-hype' ) );
Admin::number( 'suppress_dismissals', __( 'Stop for the session after', 'wp-live-hype' ), __( 'Closing this many messages silences WP Live Hype for the rest of the visit (the "Dismiss behaviour" on the Frequency tab can be stricter).', 'wp-live-hype' ), __( 'dismissals', 'wp-live-hype' ) );
Admin::number( 'suppress_repeat', __( 'Calm repeat product views after', 'wp-live-hype' ), __( 'When a visitor returns to the same product this many times, its call to action is no longer shown and that page shows at most one message.', 'wp-live-hype' ), __( 'views', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Attribution', 'wp-live-hype' ), __( 'When a visitor clicks a message, a first-party cookie (wplh_attr) holding a random ID, the A/B variant letter and a timestamp is set for the window below. If they order within it, the order is flagged as attributed. No personal data is stored, and nothing is sent to third parties.', 'wp-live-hype' ) );
Admin::toggle( 'attribution', __( 'Enable anonymous attribution', 'wp-live-hype' ), __( 'Requires Analytics to be enabled. When a consent plugin that supports the WP Consent API is active (e.g. Complianz, CookieYes), the cookie is only set after the visitor allows statistics cookies. Without such a plugin, turn this off if your cookie policy requires consent; funnel counters keep working without it.', 'wp-live-hype' ) );
echo '<div data-show-when="attribution">';
Admin::select(
	'attribution_window',
	__( 'Attribution window', 'wp-live-hype' ),
	array(
		30   => __( '30 minutes', 'wp-live-hype' ),
		60   => __( '1 hour', 'wp-live-hype' ),
		120  => __( '2 hours', 'wp-live-hype' ),
		1440 => __( '24 hours', 'wp-live-hype' ),
	)
);
echo '</div>';
Admin::card_end();

Admin::card_start( __( 'Conversion message templates', 'wp-live-hype' ), __( 'One template per line. Cart values are filled in by the visitor\'s own browser from their real cart; a line is skipped when any of its tokens has no value. Leave a box empty to restore the defaults.', 'wp-live-hype' ) );
$wplh_conv_types = array(
	'product_cta'    => __( 'Product page call to action', 'wp-live-hype' ),
	'recommend'      => __( 'Recommendations', 'wp-live-hype' ),
	'cart'           => __( 'Cart reminder', 'wp-live-hype' ),
	'checkout'       => __( 'Checkout prompt', 'wp-live-hype' ),
	'promotion'      => __( 'Free-shipping progress', 'wp-live-hype' ),
	'promotion_done' => __( 'Free-shipping minimum reached', 'wp-live-hype' ),
	'nudge'          => __( 'Inactivity message', 'wp-live-hype' ),
);
foreach ( $wplh_conv_types as $wplh_type => $wplh_label ) {
	Admin::template_editor( $wplh_type, 'tpl_' . $wplh_type, $wplh_label, implode( "\n", Templates::templates( $wplh_type ) ) );
}
Admin::card_end();

Admin::card_start( __( 'Conversion labels', 'wp-live-hype' ), __( 'The small heading above each message. Leave empty to use the translated default.', 'wp-live-hype' ) );
echo '<div class="wplh-grid-2">';
foreach ( $wplh_conv_types as $wplh_type => $wplh_label ) {
	Admin::text( 'label_' . $wplh_type, $wplh_label, '', Templates::default_label( $wplh_type ) );
}
echo '</div>';
Admin::card_end();

Admin::form_end();

Admin::card_start( __( 'Preview', 'wp-live-hype' ), __( 'Saved settings. Cart amounts in the cart and checkout previews are sample values; the free-shipping minimum is your real one when configured.', 'wp-live-hype' ) );
Admin::preview_panel(
	'wplh-preview-conversion',
	false,
	array(
		'product_cta' => __( 'Product', 'wp-live-hype' ),
		'recommend'   => __( 'Recommend', 'wp-live-hype' ),
		'cart'        => __( 'Cart', 'wp-live-hype' ),
		'promotion'   => __( 'Free shipping', 'wp-live-hype' ),
		'checkout'    => __( 'Checkout', 'wp-live-hype' ),
		'nudge'       => __( 'Inactive', 'wp-live-hype' ),
	)
);
Admin::card_end();
