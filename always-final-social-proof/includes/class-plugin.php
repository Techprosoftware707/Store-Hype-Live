<?php
/**
 * Main plugin orchestrator.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Whether hooks were registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Entry point (plugins_loaded, priority 20 — after WooCommerce loads).
	 */
	public static function bootstrap(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_woocommerce' ) );
			return;
		}
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, AFSP_MIN_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_old_woocommerce' ) );
			return;
		}

		self::register_hooks();
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'always-final-social-proof', false, dirname( AFSP_BASENAME ) . '/languages' );
	}

	/**
	 * Register all runtime hooks.
	 */
	private static function register_hooks(): void {
		// Cron & cache.
		add_filter( 'cron_schedules', array( Cache::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Minimum interval is 1 minute and configurable.
		add_action( Cache::CRON_HOOK, array( Cache::class, 'refresh' ) );
		add_action( Cache::SOON_HOOK, array( Cache::class, 'refresh_soon' ) );
		add_action( Installer::MAINTENANCE_HOOK, array( Analytics::class, 'purge_old' ) );
		add_action( 'init', array( __CLASS__, 'on_init' ), 20 );

		// Settings lifecycle.
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'on_settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, array( __CLASS__, 'on_settings_added' ), 10, 2 );

		// Keep notifications honest when store data changes.
		add_action( 'woocommerce_order_status_changed', array( Cache::class, 'on_order_status_changed' ), 10, 3 );
		add_action( 'woocommerce_trash_order', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_delete_order', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_update_product', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_product_set_stock_status', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_variation_set_stock_status', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status' ), 10, 3 );

		// REST API.
		add_action( 'rest_api_init', array( Rest_Api::class, 'register_routes' ) );

		if ( is_admin() ) {
			require_once AFSP_PATH . 'admin/class-admin.php';
			Admin::init();
		} else {
			Frontend::init();
		}
	}

	/**
	 * Init tasks: upgrades and self-healing cron schedules (admin/cron only).
	 */
	public static function on_init(): void {
		if ( is_admin() || wp_doing_cron() ) {
			Installer::maybe_upgrade();
			if ( Settings::get( 'enabled' ) ) {
				Cache::ensure_schedule();
			}
			if ( ! wp_next_scheduled( Installer::MAINTENANCE_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Installer::MAINTENANCE_HOOK );
			}
		}
	}

	/**
	 * Settings changed: drop caches, reschedule refresh if the interval changed.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public static function on_settings_updated( $old_value, $new_value ): void {
		Settings::flush();
		Cache::invalidate();
		$old_ttl = is_array( $old_value ) && isset( $old_value['cache_ttl'] ) ? (int) $old_value['cache_ttl'] : 0;
		$new_ttl = is_array( $new_value ) && isset( $new_value['cache_ttl'] ) ? (int) $new_value['cache_ttl'] : 0;
		if ( $old_ttl !== $new_ttl ) {
			Cache::ensure_schedule( true );
		}
		Logger::debug(
			'Settings updated.',
			array(
				'country_filter'   => (bool) Settings::get( 'country_filter' ),
				'target_country'   => (string) Settings::get( 'target_country' ),
				'unfiltered_scope' => (string) Settings::get( 'unfiltered_scope' ),
				'location_level'   => (string) Settings::get( 'location_level' ),
			)
		);
	}

	/**
	 * Settings created for the first time.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public static function on_settings_added( $option, $value ): void {
		unset( $option, $value );
		Settings::flush();
		Cache::invalidate();
	}

	/**
	 * A product was published/unpublished/trashed: refresh soon so it is
	 * added to or removed from notifications promptly.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_post_status( $new_status, $old_status, $post ): void {
		if ( $new_status !== $old_status && $post instanceof \WP_Post && 'product' === $post->post_type && ( 'publish' === $new_status || 'publish' === $old_status ) ) {
			Cache::schedule_soon();
		}
	}

	/**
	 * Admin notice: WooCommerce missing.
	 */
	public static function notice_missing_woocommerce(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'ALWAYS FINAL Social Proof requires WooCommerce.', 'always-final-social-proof' ) . '</p></div>';
	}

	/**
	 * Admin notice: WooCommerce too old.
	 */
	public static function notice_old_woocommerce(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html(
			sprintf(
				/* translators: %s: minimum WooCommerce version. */
				__( 'ALWAYS FINAL Social Proof requires WooCommerce %s or newer. The plugin is inactive until WooCommerce is updated.', 'always-final-social-proof' ),
				AFSP_MIN_WC_VERSION
			)
		) . '</p></div>';
	}
}
