<?php
/**
 * Data sources tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_never = array(
	__( 'Pending payment', 'wp-live-hype' ) => __( 'never used', 'wp-live-hype' ),
	__( 'Failed', 'wp-live-hype' )          => __( 'never used', 'wp-live-hype' ),
	__( 'Cancelled', 'wp-live-hype' )       => __( 'never used', 'wp-live-hype' ),
	__( 'Refunded', 'wp-live-hype' )        => __( 'never used', 'wp-live-hype' ),
	__( 'Draft', 'wp-live-hype' )           => __( 'never used', 'wp-live-hype' ),
	__( 'Trash', 'wp-live-hype' )           => __( 'never used', 'wp-live-hype' ),
);

Admin::form_start( 'data' );

Admin::card_start( __( 'Qualifying orders', 'wp-live-hype' ), __( 'Only orders with these statuses, within the lookback period and your geographic scope, can appear as purchases.', 'wp-live-hype' ) );
Admin::checkboxes( 'order_statuses', __( 'Qualifying order statuses', 'wp-live-hype' ), Settings::selectable_order_statuses(), __( '"On hold" orders are placed but not yet paid; enable with care.', 'wp-live-hype' ), $wplh_never );
Admin::select(
	'lookback_hours',
	__( 'Lookback period', 'wp-live-hype' ),
	array(
		6   => __( '6 hours', 'wp-live-hype' ),
		12  => __( '12 hours', 'wp-live-hype' ),
		24  => __( '24 hours', 'wp-live-hype' ),
		72  => __( '3 days', 'wp-live-hype' ),
		168 => __( '7 days', 'wp-live-hype' ),
		336 => __( '14 days', 'wp-live-hype' ),
		720 => __( '30 days', 'wp-live-hype' ),
	),
	__( 'Only orders placed within this period count as "recent".', 'wp-live-hype' )
);
Admin::number( 'max_records', __( 'Maximum recent purchases kept', 'wp-live-hype' ), __( 'The most recent qualifying purchases kept in the cached dataset.', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Popular products', 'wp-live-hype' ), __( 'A product is only called popular when real orders support it. Numbers are never displayed or invented.', 'wp-live-hype' ) );
Admin::select(
	'popular_source',
	__( 'Popularity based on', 'wp-live-hype' ),
	array(
		'24h'      => __( 'Sales in the last 24 hours', 'wp-live-hype' ),
		'7d'       => __( 'Sales in the last 7 days', 'wp-live-hype' ),
		'30d'      => __( 'Sales in the last 30 days', 'wp-live-hype' ),
		'lifetime' => __( 'Lifetime sales (WooCommerce total sales)', 'wp-live-hype' ),
	),
	__( 'Time-based windows respect country targeting. Lifetime sales are store-wide, so they are only used when no geographic restriction applies.', 'wp-live-hype' )
);
Admin::number( 'popular_min_sales', __( 'Minimum qualifying orders', 'wp-live-hype' ), __( 'A product needs at least this many qualifying orders in the window to be described as popular.', 'wp-live-hype' ) );
Admin::number( 'popular_count', __( 'Maximum popular products', 'wp-live-hype' ) );
Admin::products( 'popular_products', __( 'Manual product selection (optional)', 'wp-live-hype' ), __( 'Limit popular notifications to these products. They are still only shown when real sales data meets the threshold.', 'wp-live-hype' ) );
Admin::card_end();

Admin::form_end();
