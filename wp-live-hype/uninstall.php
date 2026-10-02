<?php
/**
 * Uninstall WP Live Hype.
 *
 * Removes only what the administrator chose on the Advanced tab:
 *  - plugin settings,
 *  - cached notification data,
 *  - anonymous analytics.
 *
 * WooCommerce orders, customers, products and product metadata are never
 * touched. Scheduled events are always removed.
 *
 * @package AlwaysFinal\LiveHype
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Uninstall routine for one site.
 */
function wplh_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'wplh_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	$delete_settings  = ! empty( $settings['uninstall_delete_settings'] );
	$delete_cache     = ! array_key_exists( 'uninstall_delete_cache', $settings ) || ! empty( $settings['uninstall_delete_cache'] );
	$delete_analytics = ! empty( $settings['uninstall_delete_analytics'] );

	// Scheduled events are always removed — nothing should run after uninstall.
	foreach ( array( 'wplh_refresh_dataset', 'wplh_refresh_dataset_soon', 'wplh_daily_maintenance' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	if ( $delete_cache ) {
		// Plugin transients only (prefix wplh_), e.g. the cached dataset and rate-limit counters.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name = %s",
				$wpdb->esc_like( '_transient_wplh_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wplh_' ) . '%',
				'wplh_build_lock'
			)
		);
		delete_option( 'wplh_cache_version' );
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'wplh' );
		}
	}

	if ( $delete_analytics ) {
		$table = $wpdb->prefix . 'wplh_analytics';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	if ( $delete_settings ) {
		delete_option( 'wplh_settings' );
		delete_option( 'wplh_db_version' );
		delete_option( 'wplh_activation_notice' );
	}
}

if ( is_multisite() ) {
	$wplh_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wplh_sites as $wplh_site_id ) {
		switch_to_blog( (int) $wplh_site_id );
		wplh_uninstall_site();
		restore_current_blog();
	}
} else {
	wplh_uninstall_site();
}
