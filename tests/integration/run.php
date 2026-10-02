<?php
/**
 * Integration tests for WP Live Hype.
 *
 * Runs inside a real WordPress + WooCommerce install seeded by
 * tests/setup-wordpress.sh:
 *
 *     wp eval-file tests/integration/run.php
 *
 * Exits with status 1 when any assertion fails.
 *
 * @package AlwaysFinal\LiveHype
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.Security.EscapeOutput, WordPress.DB.DirectDatabaseQuery

use AlwaysFinal\LiveHype\Cache;
use AlwaysFinal\LiveHype\Conversion;
use AlwaysFinal\LiveHype\Notifications;
use AlwaysFinal\LiveHype\Rest_Api;
use AlwaysFinal\LiveHype\Settings;
use AlwaysFinal\LiveHype\Templates;

// wp eval-file runs this file inside a function: keep counters in $GLOBALS.
$GLOBALS['wplh_failures'] = 0;
$GLOBALS['wplh_passes']   = 0;

/**
 * Record an assertion.
 *
 * @param bool   $ok   Result.
 * @param string $name Description.
 */
function check( bool $ok, string $name ): void {
	if ( $ok ) {
		++$GLOBALS['wplh_passes'];
		echo "  ok   {$name}\n";
	} else {
		++$GLOBALS['wplh_failures'];
		echo "  FAIL {$name}\n";
	}
}

global $wpdb;
echo "WP Live Hype integration tests\n";

// Install.
check( class_exists( Conversion::class ), 'plugin loaded' );
check( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wplh_analytics' ) ), 'analytics table exists' );
check( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wplh_funnel' ) ), 'funnel table exists' );
check( 'hybrid' === Settings::defaults()['activity_mode'], 'Hybrid is the default mode' );

// Store facts.
Cache::invalidate();
$data = Cache::rebuild_now();
check( isset( $data['free_shipping']['min'] ) && 299.0 === (float) $data['free_shipping']['min'], 'free-shipping minimum read from WooCommerce (299)' );
check( count( $data['purchases'] ) >= 12, 'real purchases collected' );
check( 'CA' === Settings::get( 'target_country' ), 'target country is Canada' );

// "Just bought!" notices and real counts.
$queue    = Notifications::queue(
	array(
		'type'       => 'home',
		'product_id' => 0,
		'term_id'    => 0,
	)
);
$messages = wp_list_pluck( $queue, 'message' );
$labels   = array_unique( wp_list_pluck( wp_list_filter( $queue, array( 'type' => 'purchase' ) ), 'label' ) );
check( array( 'Just bought!' ) === array_values( $labels ), 'purchase label is "Just bought!"' );
check( (bool) preg_grep( '/bought 3 × /', $messages ), 'real quantity shown when 2 or more' );
check( ! preg_grep( '/bought 1 × /', $messages ), 'quantity of 1 is not shown' );
$sold = preg_grep( '/^\d+ sold in the last 7 days: /', $messages );
check( (bool) $sold, 'units-sold notice present' );

// Units sold match an independent count.
$ok_counts = true;
foreach ( $sold as $line ) {
	preg_match( '/^(\d+) sold in the last 7 days: (.+)$/', $line, $m );
	$product_id = 0;
	foreach ( $data['products'] as $id => $public ) {
		if ( $public['name'] === $m[2] ) {
			$product_id = (int) $id;
		}
	}
	$units = 0;
	foreach ( wc_get_orders( array( 'status' => array( 'wc-processing', 'wc-completed' ), 'limit' => -1, 'date_created' => '>=' . ( time() - WEEK_IN_SECONDS ) ) ) as $order ) {
		if ( 'CA' !== $order->get_billing_country() ) {
			continue;
		}
		foreach ( $order->get_items() as $item ) {
			if ( (int) $item->get_product_id() === $product_id ) {
				$units += $item->get_quantity();
			}
		}
	}
	$ok_counts = $ok_counts && (int) $m[1] === $units;
}
check( $ok_counts, 'units sold match the orders' );

// Country confinement: an order from another country never appears.
$us = wc_create_order();
$us->add_product( wc_get_product( (int) array_key_first( $data['products'] ) ), 7 );
$us->set_billing_country( 'US' );
$us->set_billing_state( 'NY' );
$us->set_billing_city( 'Buffalo' );
$us->calculate_totals();
$us->set_status( 'processing' );
$us->save();
Cache::invalidate();
Cache::rebuild_now();
$queue = Notifications::queue(
	array(
		'type'       => 'home',
		'product_id' => 0,
		'term_id'    => 0,
	)
);
check( ! preg_grep( '/New York|Buffalo|7 ×/', wp_list_pluck( $queue, 'message' ) ), 'orders outside the target country are never used' );
$us->delete( true );

// Privacy: public fields only.
$allowed = array( 'id', 'type', 'product', 'url', 'image', 'message', 'label', 'location', 'country', 'region', 'city', 'timestamp', 'time_ago', 'addUrl', 'product_id', 'image_w', 'image_h', 'verified', 'synthetic', 'price', 'onSale' );
$extra   = array();
foreach ( $queue as $item ) {
	$extra = array_merge( $extra, array_diff( array_keys( Rest_Api::public_fields( $item ) ), $allowed ) );
}
check( empty( $extra ), 'REST items expose only whitelisted fields' );
$blob = wp_json_encode( array_map( array( Rest_Api::class, 'public_fields' ), $queue ) );
check( false === strpos( $blob, '@' ) && ! preg_match( '/#\d{2,}/', $blob ), 'no emails or order numbers in public data' );

// REST.
$request = new WP_REST_Request( 'GET', '/wp-live-hype/v1/activity' );
$request->set_query_params(
	array(
		'ctx' => 'product',
		'pid' => (int) array_keys( $data['products'] )[1],
	)
);
$response = rest_do_request( $request );
check( 200 === $response->get_status() && isset( $response->get_data()['context'] ), 'activity endpoint returns product context' );
$bad = new WP_REST_Request( 'GET', '/wp-live-hype/v1/activity' );
$bad->set_query_params( array( 'pid' => 'abc' ) );
check( 400 === rest_do_request( $bad )->get_status(), 'invalid input is rejected (400)' );

// Templates.
check( true !== Templates::validate_line( 'purchase', 'Someone bought something' ), 'template without {product} is rejected' );
check( true !== Templates::validate_line( 'promotion', 'Free shipping soon' ), 'free-shipping template needs {amount_remaining}' );
check( true !== Templates::validate_line( 'purchase', '{customer} bought {product}' ), 'unknown tokens are rejected' );
check( null === Templates::render( '{product} — {count} sold', array( 'product' => 'X' ) ), 'templates with missing data are skipped' );

// Funnel input validation.
$batch = Conversion::validate_batch(
	array(
		'funnel' => array(
			array( 'e' => 'purchase_attr', 'v' => 'a', 'd' => 'm' ),
			array( 'e' => 'atc', 'v' => 'z', 'd' => 'q' ),
			array( 'e' => '<script>', 'v' => 'a', 'd' => 'd' ),
		),
	)
);
check( array( 'atc|-|x' => 1 ) === $batch, 'browsers cannot report purchases; bad values normalised' );

// Statistics.
$same = Conversion::ztest( 50, 1000, 50, 1000 );
$diff = Conversion::ztest( 20, 1000, 80, 1000 );
check( ! $same['significant'] && $same['p'] > 0.9, 'z-test: equal rates not significant' );
check( $diff['significant'] && $diff['z'] > 0, 'z-test: large difference significant' );
check( ! Conversion::ztest( 1, 10, 9, 10 )['enough'], 'z-test: small samples not judged' );

// Product context.
$context = Conversion::product_context( (int) array_keys( $data['products'] )[1] );
check( ! empty( $context['product']['addUrl'] ), 'simple product gets an add-to-cart link (click only)' );
check( ! in_array( (int) array_keys( $data['products'] )[1], wp_list_pluck( $context['related'], 'product_id' ), true ), 'recommendations exclude the product itself' );

// Attribution.
$_COOKIE[ Conversion::COOKIE ] = '0123456789abcdef.b.m.' . time();
$order                         = wc_create_order();
$order->add_product( wc_get_product( (int) array_key_first( $data['products'] ) ), 1 );
$order->set_billing_country( 'CA' );
$order->calculate_totals();
$order->save();
Conversion::attribute_order( $order );
check( 'b' === wc_get_order( $order->get_id() )->get_meta( Conversion::ORDER_META ), 'order attributed with variant letter only' );
$_COOKIE[ Conversion::COOKIE ] = '0123456789abcdef.a.m.' . ( time() - 3 * DAY_IN_SECONDS );
$late                          = wc_create_order();
$late->save();
Conversion::attribute_order( $late );
check( '' === wc_get_order( $late->get_id() )->get_meta( Conversion::ORDER_META ), 'expired attribution is ignored' );
$order->delete( true );
$late->delete( true );
unset( $_COOKIE[ Conversion::COOKIE ] );

echo "\n{$GLOBALS['wplh_passes']} passed, {$GLOBALS['wplh_failures']} failed\n";
if ( $GLOBALS['wplh_failures'] > 0 ) {
	exit( 1 );
}
