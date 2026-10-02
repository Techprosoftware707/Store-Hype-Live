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
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend controller.
 */
final class Frontend {

	const HANDLE = 'afsp-notifications';

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
		$preview = self::requested_preview();
		if ( '' === $preview && ! Targeting::should_display() ) {
			return;
		}

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( AFSP_PATH . 'public/js/notifications.min.js' ) ? '' : '.min';
		wp_register_script(
			self::HANDLE,
			AFSP_URL . 'public/js/notifications' . $suffix . '.js',
			array(),
			AFSP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = self::config();
		if ( '' !== $preview ) {
			$config['preview']       = array_values( array_filter( array( Notifications::preview_items()[ $preview ] ?? null ) ) );
			$config['freq']['first'] = 1;
		}

		wp_add_inline_script( self::HANDLE, 'window.afspConfig = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * On-site preview requested by an administrator via ?afsp_preview=type.
	 *
	 * Display-only and limited to users who can manage the plugin; the
	 * preview toast is labelled "PREVIEW — NOT REAL CUSTOMER ACTIVITY".
	 *
	 * @return string purchase|sale|popular or empty string.
	 */
	private static function requested_preview(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, capability-gated display toggle.
		$type = isset( $_GET['afsp_preview'] ) ? sanitize_key( wp_unslash( $_GET['afsp_preview'] ) ) : '';
		if ( '' === $type || ! in_array( $type, array( 'purchase', 'sale', 'popular' ), true ) || ! Security::can_manage() ) {
			return '';
		}
		return $type;
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

		return array(
			'rest'        => esc_url_raw( rest_url( Rest_Api::NAMESPACE_V1 . '/notifications' ) ),
			'events'      => esc_url_raw( rest_url( Rest_Api::NAMESPACE_V1 . '/events' ) ),
			'css'         => esc_url_raw( AFSP_URL . 'public/css/notifications.css?ver=' . AFSP_VERSION ),
			'nonce'       => Settings::get( 'admin_only' ) ? wp_create_nonce( 'wp_rest' ) : '',
			'ctx'         => array(
				't' => in_array( $context['type'], Rest_Api::CONTEXTS, true ) ? $context['type'] : 'other',
				'p' => (int) $context['product_id'],
				'c' => (int) $context['term_id'],
			),
			'locale'      => $locale,
			'version'     => AFSP_VERSION . '.' . (int) get_option( Cache::VERSION_OPTION, 1 ),
			'analytics'   => (bool) Settings::get( 'analytics_enabled' ),
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
			'freq'        => array(
				'first'      => (int) Settings::get( 'first_delay' ),
				'duration'   => (int) Settings::get( 'duration' ),
				'interval'   => (int) Settings::get( 'interval' ),
				'maxSession' => (int) Settings::get( 'max_per_session' ),
				'maxPage'    => (int) Settings::get( 'max_per_page' ),
				'dismiss'    => (string) Settings::get( 'dismiss_behavior' ),
				'hover'      => (bool) Settings::get( 'pause_on_hover' ),
			),
			'i18n'        => array(
				'region'    => __( 'Store activity notifications', 'always-final-social-proof' ),
				'close'     => __( 'Close notification', 'always-final-social-proof' ),
				'verified'  => __( 'Verified purchase', 'always-final-social-proof' ),
				'justNow'   => __( 'Just now', 'always-final-social-proof' ),
				'recently'  => __( 'Recently', 'always-final-social-proof' ),
				'hour'      => $approx['hour'],
				'hours'     => $approx['hours'],
				'day'       => $approx['day'],
				'week'      => $approx['week'],
				'month'     => $approx['month'],
				'was'       => __( 'Regular price', 'always-final-social-proof' ),
				'now'       => __( 'Sale price', 'always-final-social-proof' ),
				'save'      => __( 'Save', 'always-final-social-proof' ),
				'preview'   => __( 'PREVIEW — NOT REAL CUSTOMER ACTIVITY', 'always-final-social-proof' ),
				/* translators: %s: developer name. */
				'poweredBy' => __( 'by %s', 'always-final-social-proof' ),
			),
		);
	}
}
