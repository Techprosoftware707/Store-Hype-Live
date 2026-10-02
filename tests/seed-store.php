<?php
/**
 * Seed a test store: Canadian store, products, a free-shipping zone ($299),
 * cash on delivery, real test orders and fast notification timing.
 * Run with: wp eval-file tests/seed-store.php
 *
 * @package AlwaysFinal\LiveHype
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

update_option( 'woocommerce_default_country', 'CA:ON' );
update_option( 'woocommerce_currency', 'CAD' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ) );

$wplh_products = array();
foreach ( array( array( 'BPC-157', 60, 45 ), array( 'TB-500', 85, 0 ), array( 'Retatrutide', 120, 0 ), array( 'Sample Kit', 20, 0 ) ) as $row ) {
	$p = new WC_Product_Simple();
	$p->set_name( $row[0] );
	$p->set_status( 'publish' );
	$p->set_regular_price( (string) $row[1] );
	if ( $row[2] ) {
		$p->set_sale_price( (string) $row[2] );
	}
	$p->set_stock_status( 'instock' );
	$wplh_products[] = $p->save();
}
// Related products for recommendations.
$first = wc_get_product( $wplh_products[1] );
$first->set_upsell_ids( array( $wplh_products[0], $wplh_products[2] ) );
$first->save();

$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Canada' );
$zone->add_location( 'CA', 'country' );
$zone->save();
$flat = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'title' => 'Flat rate', 'cost' => '15', 'tax_status' => 'none' ) );
$free = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$free}_settings", array( 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '299', 'ignore_discounts' => 'no' ) );

// Real test orders (Canada) so Hybrid mode has purchases and units sold.
$places = array( array( 'ON', 'Toronto' ), array( 'QC', 'Montreal' ), array( 'BC', 'Vancouver' ), array( 'AB', 'Calgary' ) );
for ( $i = 0; $i < 12; $i++ ) {
	$order = wc_create_order();
	$order->add_product( wc_get_product( $wplh_products[ $i % 3 ] ), 0 === $i % 4 ? 3 : 1 );
	$order->set_billing_country( 'CA' );
	$order->set_billing_state( $places[ $i % 4 ][0] );
	$order->set_billing_city( $places[ $i % 4 ][1] );
	$order->calculate_totals();
	$order->set_date_created( time() - ( $i + 1 ) * HOUR_IN_SECONDS );
	$order->set_status( 'processing' );
	$order->save();
}

// Fast timing so browser tests run quickly.
$settings = get_option( 'wplh_settings', array() );
$settings = array_merge(
	is_array( $settings ) ? $settings : array(),
	array(
		'conversion_preset' => 'custom',
		'first_delay'       => 1,
		'interval'          => 5,
		'interval_max'      => 6,
		'max_per_page'      => 10,
		'max_per_session'   => 40,
		'duration'          => 4,
		'dismiss_behavior'  => 'none',
		'target_country'    => 'CA',
	)
);
update_option( 'wplh_settings', $settings );
AlwaysFinal\LiveHype\Cache::invalidate();
AlwaysFinal\LiveHype\Cache::rebuild_now();
echo 'Seeded ' . count( $wplh_products ) . " products and 12 orders.\n";
