<?php
/**
 * Main plugin orchestrator.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

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
			Logger::error( 'WooCommerce not detected; plugin is dormant.' );
			return;
		}
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WPLH_MIN_WC_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_old_woocommerce' ) );
			Logger::error( 'WooCommerce version too old; plugin is dormant.', array( 'wc_version' => WC_VERSION ) );
			return;
		}

		self::register_hooks();
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'wp-live-hype', false, dirname( WPLH_BASENAME ) . '/languages' );
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
		add_action( Installer::MAINTENANCE_HOOK, array( Conversion::class, 'purge_old' ) );
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

		// Free-shipping messages follow the live WooCommerce shipping rules.
		add_action( 'woocommerce_after_shipping_zone_object_save', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_shipping_zone_method_added', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_shipping_zone_method_deleted', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'woocommerce_shipping_zone_method_status_toggled', array( Cache::class, 'schedule_soon' ), 10, 0 );
		add_action( 'updated_option', array( __CLASS__, 'on_option_updated' ), 10, 1 );

		// Conversion attribution (order hooks).
		Conversion::init();

		// Privacy: consent plugins (WP Consent API) and the Privacy Policy Guide.
		add_filter( 'wp_consent_api_registered_' . WPLH_BASENAME, '__return_true' );
		add_action( 'init', array( __CLASS__, 'register_cookie_info' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );

		// REST API.
		add_action( 'rest_api_init', array( Rest_Api::class, 'register_routes' ) );

		if ( is_admin() ) {
			require_once WPLH_PATH . 'admin/class-admin.php';
			Admin::init();
		} else {
			Frontend::init();
		}
	}

	/**
	 * Init tasks: upgrades and self-healing cron schedules (admin/cron only).
	 */
	public static function on_init(): void {
		if ( Logger::enabled() ) {
			Logger::debug(
				'Plugin started.',
				array(
					'version'        => WPLH_VERSION,
					'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
					'hpos'           => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
					'request'        => self::request_type(),
					'enabled'        => (bool) Settings::get( 'enabled' ),
					'country_filter' => (bool) Settings::get( 'country_filter' ),
					'target_country' => (string) Settings::get( 'target_country' ),
					'scope'          => Country::allowed_countries() === false ? 'disabled' : ( null === Country::allowed_countries() ? 'all' : implode( ',', (array) Country::allowed_countries() ) ),
				)
			);
			if ( Settings::get( 'country_filter' ) && ! Country::is_valid( (string) Settings::get( 'target_country' ) ) ) {
				Logger::error( 'Configuration: country filtering is ON without a valid target country; purchase notifications are inactive.' );
			}
		}
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
	 * Request type label for debug logs.
	 *
	 * @return string
	 */
	private static function request_type(): string {
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( is_admin() ) {
			return 'admin';
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) || false !== strpos( $uri, 'rest_route=' ) ? 'rest' : 'front';
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
		Conversion::track_experiment();
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
	 * Describe the attribution cookie to consent plugins (WP Consent API).
	 */
	public static function register_cookie_info(): void {
		if ( ! function_exists( 'wp_add_cookie_info' ) || ! Settings::get( 'attribution' ) ) {
			return;
		}
		wp_add_cookie_info(
			Conversion::COOKIE,
			Settings::plugin_label(),
			'statistics',
			__( 'Up to 24 hours (set on the Conversion tab)', 'wp-live-hype' ),
			__( 'Set only when the visitor clicks a store message. Holds a random ID, an A/B test letter, a mobile/desktop flag and a timestamp, so an order placed shortly afterwards can be counted as following that click.', 'wp-live-hype' ),
			false,
			false,
			false
		);
	}

	/**
	 * Suggested text for Settings → Privacy → Policy Guide.
	 */
	public static function privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text, adjust it to your settings.', 'wp-live-hype' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'wp-live-hype' ) . '</strong> ';
		$content .= esc_html__( 'This store shows short messages about its products and its real recent sales. Those messages never contain customer names, contact details, addresses or order numbers; at most they mention a product, a region or city, a quantity and an approximate time.', 'wp-live-hype' ) . '</p>';
		$content .= '<p>' . esc_html__( 'To improve these messages we count, without identifying anyone, how often they are shown, clicked or closed, and how many visits reach product pages, the cart and checkout. Only daily totals are stored, together with an A/B test letter and whether the device was a phone or a computer. No IP address or user ID is stored and nothing is shared with third parties.', 'wp-live-hype' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If you click one of these messages, a cookie named wplh_attr is stored in your browser for a limited time (between 30 minutes and 24 hours). It contains a random ID, an A/B test letter, a device-type flag and a timestamp, and lets us count an order placed shortly afterwards as following that click. The order is marked only with the test letter. When a cookie-consent tool is used on this site, the cookie is set only after you allow statistics cookies.', 'wp-live-hype' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Your browser also keeps a small local record (localStorage) of which messages it has already shown and how far you have browsed, so messages are not repeated. This record never leaves your device.', 'wp-live-hype' ) . '</p>';
		wp_add_privacy_policy_content( Settings::plugin_label(), wp_kses_post( $content ) );
	}

	/**
	 * A free-shipping method's settings changed (option
	 * woocommerce_free_shipping_{instance}_settings): refresh soon.
	 *
	 * @param string $option Option name.
	 */
	public static function on_option_updated( $option ): void {
		if ( is_string( $option ) && 0 === strpos( $option, 'woocommerce_free_shipping_' ) ) {
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
		echo '<div class="notice notice-error"><p>' . esc_html__( 'WP Live Hype requires WooCommerce.', 'wp-live-hype' ) . '</p></div>';
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
				__( 'WP Live Hype requires WooCommerce %s or newer. The plugin is inactive until WooCommerce is updated.', 'wp-live-hype' ),
				WPLH_MIN_WC_VERSION
			)
		) . '</p></div>';
	}
}
