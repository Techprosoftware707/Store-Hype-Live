<?php
/**
 * Frontend asset loading.
 *
 * One small deferred script is enqueued only on pages where notifications
 * may appear. The stylesheet is injected by that script just before the
 * first notification is shown, so it never blocks rendering (no LCP impact),
 * and toasts are fixed-position overlays animated with transform/opacity
 * only (no layout shift).
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend controller.
 */
final class Frontend {

	const HANDLE = 'wplh-notifications';

	/**
	 * Types an administrator can preview on the storefront.
	 */
	const PREVIEW_TYPES = array( 'purchase', 'sale', 'popular', 'featured', 'explore', 'location', 'product_cta', 'recommend', 'cart', 'promotion', 'checkout', 'nudge' );

	/**
	 * Hook into WordPress.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Enqueue the frontend script when appropriate.
	 */
	public static function enqueue(): void {
		$preview    = self::requested_preview();
		$display    = '' !== $preview || Targeting::should_display();
		$track_only = ! $display && Targeting::should_track();
		if ( ! $display && ! $track_only ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( WPLH_PATH . 'public/js/notifications.min.js' ) ? '' : '.min';
		wp_register_script(
			self::HANDLE,
			WPLH_URL . 'public/js/notifications' . $suffix . '.js',
			array(),
			WPLH_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = self::config();
		if ( $track_only ) {
			$config['trackOnly'] = true;
		}
		if ( '' !== $preview ) {
			$config['preview']       = array_values( array_filter( array( array_merge( Notifications::preview_items(), Synthetic_Engine::preview_items(), Conversion::preview_items() )[ $preview ] ?? null ) ) );
			$config['freq']['first'] = 1;
		}

		wp_add_inline_script( self::HANDLE, 'window.wplhConfig = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * On-site preview requested by an administrator via ?wplh_preview=type.
	 *
	 * Display-only and limited to users who can manage the plugin; the
	 * preview toast is labelled "SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY".
	 *
	 * @return string purchase|sale|popular or empty string.
	 */
	private static function requested_preview(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, capability-gated display toggle.
		$type = isset( $_GET['wplh_preview'] ) ? sanitize_key( wp_unslash( $_GET['wplh_preview'] ) ) : '';
		if ( '' === $type || ! in_array( $type, self::PREVIEW_TYPES, true ) || ! Security::can_manage() ) {
			return '';
		}
		return $type;
	}

	/**
	 * Sound settings for the frontend.
	 *
	 * @return array
	 */
	public static function sound_config(): array {
		$choice = (string) Settings::get( 'sound_choice' );
		return array(
			'desktop' => (bool) Settings::get( 'sound_desktop' ),
			'mobile'  => (bool) Settings::get( 'sound_mobile' ),
			'volume'  => (int) Settings::get( 'sound_volume' ),
			'url'     => esc_url_raw( WPLH_URL . 'assets/audio/' . sanitize_key( $choice ) . '.mp3?ver=' . WPLH_VERSION ),
		);
	}

	/**
	 * Configuration passed to the frontend script. Contains no personal data
	 * and nothing user-specific (except the REST nonce in admin-only test
	 * mode, where pages are only rendered for logged-in administrators).
	 *
	 * @return array
	 */
	public static function config(): array {
		$context = Targeting::context();
		$locale  = str_replace( '_', '-', determine_locale() );
		$approx  = Notifications::approximate_labels();
		$timing  = Conversion::timing( (string) Settings::get( 'conversion_preset' ) );

		return array(
			'rest'        => esc_url_raw( rest_url( Rest_Api::NAMESPACE_V1 . '/activity' ) ),
			'events'      => esc_url_raw( rest_url( Rest_Api::NAMESPACE_V1 . '/events' ) ),
			'css'         => esc_url_raw( WPLH_URL . 'public/css/notifications.css?ver=' . WPLH_VERSION ),
			'nonce'       => Settings::get( 'admin_only' ) ? wp_create_nonce( 'wp_rest' ) : '',
			'ctx'         => array(
				't' => in_array( $context['type'], Rest_Api::CONTEXTS, true ) ? $context['type'] : 'other',
				'p' => (int) $context['product_id'],
				'c' => (int) $context['term_id'],
				// Order-received ("thank you") page: never counted as a checkout visit.
				'o' => function_exists( 'is_order_received_page' ) && is_order_received_page(),
			),
			'locale'      => $locale,
			'version'     => WPLH_VERSION . '.' . (int) get_option( Cache::VERSION_OPTION, 1 ),
			'analytics'   => (bool) Settings::get( 'analytics_enabled' ),
			'cooldown'    => (int) Settings::get( 'cooldown_minutes' ),
			'sound'       => self::sound_config(),
			'clientCache' => Settings::get( 'client_cache' ) ? (int) min( 300, Cache::ttl() ) : 0,
			'display'     => array(
				'position'    => (string) Settings::get( 'position_desktop' ),
				'mobile'      => (string) Settings::get( 'position_mobile' ),
				'animation'   => (string) Settings::get( 'animation' ),
				'theme'       => (string) Settings::get( 'theme' ),
				'accent'      => (string) Settings::get( 'accent_color' ),
				'bg'          => (string) Settings::get( 'bg_color' ),
				'text'        => (string) Settings::get( 'text_color' ),
				'radius'      => (int) Settings::get( 'radius' ),
				'shadow'      => (string) Settings::get( 'shadow' ),
				'width'       => (int) Settings::get( 'width' ),
				'offsetX'     => (int) Settings::get( 'offset_x' ),
				'offsetY'     => (int) Settings::get( 'offset_y' ),
				'zIndex'      => (int) Settings::get( 'z_index' ),
				'font'        => (string) Settings::get( 'font' ),
				'image'       => (bool) Settings::get( 'show_image' ),
				'shape'       => (string) Settings::get( 'image_shape' ),
				'label'       => (bool) Settings::get( 'show_label' ),
				'time'        => (bool) Settings::get( 'show_time' ),
				'timeMode'    => (string) Settings::get( 'time_display' ),
				'verified'    => (bool) Settings::get( 'show_verified' ),
				'close'       => (bool) Settings::get( 'show_close' ),
				'price'       => (bool) Settings::get( 'show_sale_price' ),
				'announce'    => (bool) Settings::get( 'a11y_announce' ),
				'attribution' => Settings::get( 'frontend_attribution' ) ? Branding::developer_name() : '',
			),
			'conv'        => Conversion::client_config(),
			'freq'        => array(
				'first'       => $timing['first'],
				'duration'    => (int) Settings::get( 'duration' ),
				'interval'    => $timing['interval'],
				'intervalMax' => $timing['intervalMax'],
				'random'      => (bool) Settings::get( 'random_timing' ),
				'maxSession'  => $timing['maxSession'],
				'maxPage'     => $timing['maxPage'],
				'dismiss'     => (string) Settings::get( 'dismiss_behavior' ),
				'hover'       => (bool) Settings::get( 'pause_on_hover' ),
			),
			'i18n'        => array(
				'region'    => __( 'Store activity notifications', 'wp-live-hype' ),
				'close'     => __( 'Close notification', 'wp-live-hype' ),
				'verified'  => __( 'Verified purchase', 'wp-live-hype' ),
				'justNow'   => __( 'Just now', 'wp-live-hype' ),
				'recently'  => __( 'Recently', 'wp-live-hype' ),
				'hour'      => $approx['hour'],
				'hours'     => $approx['hours'],
				'day'       => $approx['day'],
				'week'      => $approx['week'],
				'month'     => $approx['month'],
				'was'       => __( 'Regular price', 'wp-live-hype' ),
				'now'       => __( 'Sale price', 'wp-live-hype' ),
				'save'      => __( 'Save', 'wp-live-hype' ),
				'view'      => Conversion::cta_text( 'view' ),
				'stage'     => __( 'Shopping assistance', 'wp-live-hype' ),
				'preview'   => __( 'SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY', 'wp-live-hype' ),
				/* translators: %s: developer name. */
				'poweredBy' => __( 'by %s', 'wp-live-hype' ),
			),
		);
	}
}
