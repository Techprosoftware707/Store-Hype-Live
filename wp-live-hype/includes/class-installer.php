<?php
/**
 * Activation, deactivation and upgrades.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class Installer {

	const DB_VERSION_OPTION = 'wplh_db_version';
	const MAINTENANCE_HOOK  = 'wplh_daily_maintenance';
	const NOTICE_OPTION     = 'wplh_activation_notice';

	/**
	 * Activation hook. Never fatal: if WooCommerce is missing the plugin
	 * stays dormant and shows an admin notice.
	 *
	 * @param bool $network_wide Network activation.
	 */
	public static function activate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 500,
				)
			);
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}
		self::install_site();
	}

	/**
	 * Install for the current site.
	 */
	public static function install_site(): void {
		Analytics::create_table();
		Conversion::create_table();
		update_option( self::DB_VERSION_OPTION, WPLH_DB_VERSION, false );

		if ( false === get_option( Settings::OPTION, false ) ) {
			$defaults = Settings::defaults();
			// Default the target country to the store's base country (never hard-coded).
			$defaults['target_country'] = Country::base_country();
			add_option( Settings::OPTION, $defaults, '', true );
			add_option( self::NOTICE_OPTION, 1, '', false );
		}
		if ( false === get_option( Cache::VERSION_OPTION, false ) ) {
			add_option( Cache::VERSION_OPTION, 1, '', true );
		}
		Settings::flush();

		add_filter( 'cron_schedules', array( Cache::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		Cache::ensure_schedule( true );
		if ( ! wp_next_scheduled( self::MAINTENANCE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::MAINTENANCE_HOOK );
		}
	}

	/**
	 * A site was added to a network where the plugin is network-activated:
	 * set it up right away instead of on its first admin visit.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function on_new_site( $site ): void {
		if ( ! $site instanceof \WP_Site ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( WPLH_BASENAME ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Deactivation hook: remove scheduled events. Data is kept.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( Cache::CRON_HOOK );
		wp_clear_scheduled_hook( Cache::SOON_HOOK );
		wp_clear_scheduled_hook( self::MAINTENANCE_HOOK );
	}

	/**
	 * Run upgrades when the stored DB version differs (also covers sites
	 * added to a network after activation).
	 */
	public static function maybe_upgrade(): void {
		if ( WPLH_DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			self::install_site();
		}
	}
}
