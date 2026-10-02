<?php
/**
 * Data sources tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_never = array(
	__( 'Pending payment', 'always-final-social-proof' ) => __( 'never used', 'always-final-social-proof' ),
	__( 'Failed', 'always-final-social-proof' )          => __( 'never used', 'always-final-social-proof' ),
	__( 'Cancelled', 'always-final-social-proof' )       => __( 'never used', 'always-final-social-proof' ),
	__( 'Refunded', 'always-final-social-proof' )        => __( 'never used', 'always-final-social-proof' ),
	__( 'Draft', 'always-final-social-proof' )           => __( 'never used', 'always-final-social-proof' ),
	__( 'Trash', 'always-final-social-proof' )           => __( 'never used', 'always-final-social-proof' ),
);

Admin::form_start( 'data' );

Admin::card_start( __( 'Qualifying orders', 'always-final-social-proof' ), __( 'Only orders with these statuses, within the lookback period and your geographic scope, can appear as purchases.', 'always-final-social-proof' ) );
Admin::checkboxes( 'order_statuses', __( 'Qualifying order statuses', 'always-final-social-proof' ), Settings::selectable_order_statuses(), __( '"On hold" orders are placed but not yet paid; enable with care.', 'always-final-social-proof' ), $afsp_never );
Admin::select(
	'lookback_hours',
	__( 'Lookback period', 'always-final-social-proof' ),
	array(
		6   => __( '6 hours', 'always-final-social-proof' ),
		12  => __( '12 hours', 'always-final-social-proof' ),
		24  => __( '24 hours', 'always-final-social-proof' ),
		72  => __( '3 days', 'always-final-social-proof' ),
		168 => __( '7 days', 'always-final-social-proof' ),
		336 => __( '14 days', 'always-final-social-proof' ),
		720 => __( '30 days', 'always-final-social-proof' ),
	),
	__( 'Only orders placed within this period count as "recent".', 'always-final-social-proof' )
);
Admin::number( 'max_records', __( 'Maximum recent purchases kept', 'always-final-social-proof' ), __( 'The most recent qualifying purchases kept in the cached dataset.', 'always-final-social-proof' ) );
Admin::card_end();

Admin::card_start( __( 'Popular products', 'always-final-social-proof' ), __( 'A product is only called popular when real orders support it. Numbers are never displayed or invented.', 'always-final-social-proof' ) );
Admin::select(
	'popular_source',
	__( 'Popularity based on', 'always-final-social-proof' ),
	array(
		'24h'      => __( 'Sales in the last 24 hours', 'always-final-social-proof' ),
		'7d'       => __( 'Sales in the last 7 days', 'always-final-social-proof' ),
		'30d'      => __( 'Sales in the last 30 days', 'always-final-social-proof' ),
		'lifetime' => __( 'Lifetime sales (WooCommerce total sales)', 'always-final-social-proof' ),
	),
	__( 'Time-based windows respect country targeting. Lifetime sales are store-wide, so they are only used when no geographic restriction applies.', 'always-final-social-proof' )
);
Admin::number( 'popular_min_sales', __( 'Minimum qualifying orders', 'always-final-social-proof' ), __( 'A product needs at least this many qualifying orders in the window to be described as popular.', 'always-final-social-proof' ) );
Admin::number( 'popular_count', __( 'Maximum popular products', 'always-final-social-proof' ) );
Admin::products( 'popular_products', __( 'Manual product selection (optional)', 'always-final-social-proof' ), __( 'Limit popular notifications to these products. They are still only shown when real sales data meets the threshold.', 'always-final-social-proof' ) );
Admin::card_end();

Admin::form_end();
