<?php
/**
 * Live Hype engine tab: activity mode and stream composition.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_data    = Cache::get_dataset( false );
$wplh_pool    = count( (array) ( $wplh_data['pool']['products'] ?? array() ) );
$wplh_country = Locations::engine_country( new Rng( 1 ) );
$wplh_ships   = Locations::store_ships_to( $wplh_country );

Admin::form_start( 'engine' );
?>
<section class="wplh-card wplh-country-hero">
	<div class="wplh-country-hero__head">
		<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
		<div>
			<h2 class="wplh-card__title"><?php esc_html_e( 'Activity mode', 'wp-live-hype' ); ?></h2>
			<p class="wplh-card__intro"><?php esc_html_e( 'Choose what powers the live activity layer. Every mode is privacy-safe: visitors never receive customer or order information.', 'wp-live-hype' ); ?></p>
		</div>
	</div>
	<?php
	Admin::radios(
		'activity_mode',
		__( 'Mode', 'wp-live-hype' ),
		array(
			'synthetic' => array( __( 'Synthetic', 'wp-live-hype' ), __( 'A continuous, weighted rotation built from your catalogue: featured products, spotlights, shipping-region messages and genuine sales. Works on a brand-new store. These events never claim that someone bought or viewed something.', 'wp-live-hype' ) ),
			'hybrid'    => array( __( 'Hybrid (default)', 'wp-live-hype' ), __( 'Real store activity ("Just bought!" purchases, units sold, genuine sales) leads; the synthetic rotation fills the quiet periods in between. On a store without orders yet, it behaves like Synthetic.', 'wp-live-hype' ) ),
			'aggregate' => array( __( 'Aggregate', 'wp-live-hype' ), __( 'Only activity supported by your real WooCommerce data. Silent when there is none.', 'wp-live-hype' ) ),
		)
	);
	?>
	<div class="wplh-callout wplh-callout--info">
		<span class="dashicons dashicons-shield" aria-hidden="true"></span>
		<p><?php esc_html_e( 'Activity wording ("Recent activity", "popular", "2 min ago") is only used for events backed by real store data. Synthetic events use promotional wording and carry no times, so the layer stays lively without telling shoppers anything untrue.', 'wp-live-hype' ); ?></p>
	</div>
</section>
<?php
Admin::card_start( __( 'Synthetic rotation', 'wp-live-hype' ), __( 'Event types the engine mixes together. Types alternate so the same kind never appears twice in a row.', 'wp-live-hype' ) );
Admin::toggle( 'promo_featured', __( 'Featured products', 'wp-live-hype' ), __( 'e.g. "Retatrutide — featured in our store". Uses products weighted High or Featured first.', 'wp-live-hype' ) );
Admin::toggle( 'promo_explore', __( 'Product spotlights', 'wp-live-hype' ), __( 'e.g. "Explore BPC-157".', 'wp-live-hype' ) );
Admin::toggle(
	'promo_location',
	__( 'Shipping-region messages', 'wp-live-hype' ),
	$wplh_ships
		? __( 'e.g. "Ships across Ontario". Locations come from the Country and Weighting tabs.', 'wp-live-hype' )
		: __( 'Inactive: your WooCommerce shipping settings do not include the target country, so these messages would not be true.', 'wp-live-hype' )
);
Admin::toggle( 'promo_sale', __( 'Genuine sales', 'wp-live-hype' ), __( 'Products with a real, active WooCommerce sale price. Discounts are never invented.', 'wp-live-hype' ) );
echo '<div data-show-when-value="activity_mode=hybrid">';
Admin::number( 'promo_ratio', __( 'Real events per synthetic event (Hybrid)', 'wp-live-hype' ), __( 'How many real activity events appear before a synthetic event fills the gap. When real activity runs out, the synthetic rotation continues on its own.', 'wp-live-hype' ) );
echo '</div>';
Admin::number( 'cooldown_minutes', __( 'Repeat cooldown', 'wp-live-hype' ), __( 'The same product/location/message combination is not shown to a visitor again within this time.', 'wp-live-hype' ), __( 'minutes', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Aggregate WooCommerce data', 'wp-live-hype' ) );
Admin::toggle( 'use_aggregate', __( 'Use Aggregate WooCommerce Data', 'wp-live-hype' ), __( 'Aggregate store statistics may influence synthetic activity frequency. Individual customer and order information is never displayed.', 'wp-live-hype' ) );
Admin::card_end();

Admin::form_end();
?>
<p class="wplh-meta-row">
	<?php
	/* translators: %d: number of products. */
	echo esc_html( sprintf( _n( '%d product is currently available to the engine.', '%d products are currently available to the engine.', $wplh_pool, 'wp-live-hype' ), $wplh_pool ) );
	?>
</p>
