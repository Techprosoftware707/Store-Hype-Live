<?php
/**
 * Uninstall ALWAYS FINAL Social Proof.
 *
 * Removes only what the administrator chose on the Advanced tab:
 *  - plugin settings,
 *  - cached notification data,
 *  - anonymous analytics.
 *
 * WooCommerce orders, customers, products and product metadata are never
 * touched. Scheduled events are always removed.
 *
 * @package AlwaysFinal\SocialProof
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Uninstall routine for one site.
 */
function afsp_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'afsp_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	$delete_settings  = ! empty( $settings['uninstall_delete_settings'] );
	$delete_cache     = ! array_key_exists( 'uninstall_delete_cache', $settings ) || ! empty( $settings['uninstall_delete_cache'] );
	$delete_analytics = ! empty( $settings['uninstall_delete_analytics'] );

	// Scheduled events are always removed — nothing should run after uninstall.
	foreach ( array( 'afsp_refresh_dataset', 'afsp_refresh_dataset_soon', 'afsp_daily_maintenance' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	if ( $delete_cache ) {
		// Plugin transients only (prefix afsp_), e.g. the cached dataset and rate-limit counters.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name = %s",
				$wpdb->esc_like( '_transient_afsp_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_afsp_' ) . '%',
				'afsp_build_lock'
			)
		);
		delete_option( 'afsp_cache_version' );
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'afsp' );
		}
	}

	if ( $delete_analytics ) {
		$table = $wpdb->prefix . 'afsp_analytics';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	if ( $delete_settings ) {
		delete_option( 'afsp_settings' );
		delete_option( 'afsp_db_version' );
		delete_option( 'afsp_activation_notice' );
	}
}

if ( is_multisite() ) {
	$afsp_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $afsp_sites as $afsp_site_id ) {
		switch_to_blog( (int) $afsp_site_id );
		afsp_uninstall_site();
		restore_current_blog();
	}
} else {
	afsp_uninstall_site();
}
