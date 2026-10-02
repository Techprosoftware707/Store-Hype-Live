<?php
/**
 * Admin interface: WooCommerce → WP Live Hype.
 *
 * Settings are saved through the WordPress Settings API (options.php), which
 * enforces the nonce; the capability is mapped to `manage_woocommerce` via
 * `option_page_capability_{group}`. Every custom action verifies a nonce and
 * the capability before doing anything.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class Admin {

	const PAGE  = 'wp-live-hype';
	const NONCE = 'wplh_admin';

	/**
	 * Menu hook suffix.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Register admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 70 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'option_page_capability_' . Settings::GROUP, array( __CLASS__, 'settings_capability' ) );
		add_action( 'admin_post_wplh_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_wplh_reset_analytics', array( __CLASS__, 'handle_reset_analytics' ) );
		add_action( 'wp_ajax_wplh_preview', array( __CLASS__, 'ajax_preview' ) );
		add_filter( 'plugin_action_links_' . WPLH_BASENAME, array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ), 99 );
		add_filter( 'update_footer', array( __CLASS__, 'footer_version' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Capability required to save the settings group.
	 *
	 * @return string
	 */
	public static function settings_capability(): string {
		return Security::capability();
	}

	/**
	 * Tabs.
	 *
	 * @return array<string,string>
	 */
	public static function tabs(): array {
		$tabs = array(
			'dashboard'     => __( 'Dashboard', 'wp-live-hype' ),
			'general'       => __( 'General', 'wp-live-hype' ),
			'engine'        => __( 'Live Hype', 'wp-live-hype' ),
			'weighting'     => __( 'Weighting', 'wp-live-hype' ),
			'country'       => __( 'Country', 'wp-live-hype' ),
			'notifications' => __( 'Notifications', 'wp-live-hype' ),
			'display'       => __( 'Display', 'wp-live-hype' ),
			'sound'         => __( 'Sound', 'wp-live-hype' ),
			'frequency'     => __( 'Frequency', 'wp-live-hype' ),
			'products'      => __( 'Products', 'wp-live-hype' ),
			'data'          => __( 'Data', 'wp-live-hype' ),
			'analytics'     => __( 'Analytics', 'wp-live-hype' ),
			'advanced'      => __( 'Advanced', 'wp-live-hype' ),
			'branding'      => __( 'Branding', 'wp-live-hype' ),
		);
		if ( ! Branding::tab_available() ) {
			unset( $tabs['branding'] );
		}
		return $tabs;
	}

	/**
	 * Current tab slug.
	 *
	 * @return string
	 */
	public static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		return array_key_exists( $tab, self::tabs() ) ? $tab : 'dashboard';
	}

	/**
	 * Admin URL for a tab.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( string $tab = 'dashboard', array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Register the submenu under WooCommerce.
	 */
	public static function menu(): void {
		$label      = Settings::plugin_label();
		self::$hook = (string) add_submenu_page(
			'woocommerce',
			$label,
			$label,
			Security::capability(),
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Whether the current screen is the plugin page.
	 *
	 * @return bool
	 */
	private static function is_plugin_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && '' !== self::$hook && self::$hook === $screen->id;
	}

	/**
	 * Enqueue admin assets on the plugin page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ): void {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_style( 'wplh-admin', WPLH_URL . 'admin/css/admin.css', array(), WPLH_VERSION );
		wp_enqueue_style( 'wplh-notifications', WPLH_URL . 'public/css/notifications.css', array(), WPLH_VERSION );

		// WooCommerce's searchable selects (selectWoo) for countries, products and categories.
		if ( defined( 'WC_PLUGIN_FILE' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		$frontend = Frontend::config();
		wp_register_script( 'wplh-notifications', WPLH_URL . 'public/js/notifications.js', array(), WPLH_VERSION, true );
		wp_add_inline_script(
			'wplh-notifications',
			'window.wplhConfig = ' . wp_json_encode(
				array(
					'adminPreview' => true,
					'display'      => $frontend['display'],
					'i18n'         => $frontend['i18n'],
					'locale'       => $frontend['locale'],
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);

		wp_enqueue_script( 'wplh-admin', WPLH_URL . 'admin/js/admin.js', array( 'jquery', 'wplh-notifications' ), WPLH_VERSION, true );

		$countries = array();
		foreach ( Country::countries() as $code => $name ) {
			$countries[ $code ] = $name;
		}
		$preview_items = array_map(
			static function ( $item ) {
				return is_array( $item ) ? array_merge( Rest_Api::public_fields( $item ), array( 'preview' => true ) ) : null;
			},
			array_merge( Notifications::preview_items(), Synthetic_Engine::preview_items() )
		);

		wp_localize_script(
			'wplh-admin',
			'wplhAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'preview'   => $preview_items,
				'countries' => $countries,
				'mode'      => (string) Settings::get( 'activity_mode' ),
				'sounds'    => array(
					'chime'  => WPLH_URL . 'assets/audio/chime.mp3',
					'modern' => WPLH_URL . 'assets/audio/modern.mp3',
					'pop'    => WPLH_URL . 'assets/audio/pop.mp3',
					'clean'  => WPLH_URL . 'assets/audio/clean.mp3',
				),
				'i18n'      => array(
					/* translators: %s: country name. */
					'eligibleOnly'   => __( '%s orders only', 'wp-live-hype' ),
					'eligibleAll'    => __( 'All valid store orders (any country)', 'wp-live-hype' ),
					'eligibleNone'   => __( 'None — select a target country', 'wp-live-hype' ),
					'confirmReset'   => __( 'Delete all analytics data? This cannot be undone.', 'wp-live-hype' ),
					'previewFailed'  => __( 'Preview could not be loaded.', 'wp-live-hype' ),
					'previewHidden'  => __( 'Notifications are hidden on mobile with the current settings.', 'wp-live-hype' ),
					'previewMissing' => __( 'No preview available for this type yet — it needs published products (and, for shipping messages, a country your store ships to).', 'wp-live-hype' ),
				),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render(): void {
		if ( ! Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-live-hype' ) );
		}
		$tab      = self::current_tab();
		$settings = Settings::all();
		include WPLH_PATH . 'admin/views/page.php';
	}

	/**
	 * Admin-post: rebuild the dataset now.
	 */
	public static function handle_refresh(): void {
		if ( ! Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-live-hype' ), 403 );
		}
		check_admin_referer( 'wplh_refresh' );
		Cache::invalidate();
		Cache::rebuild_now();
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'dashboard';
		wp_safe_redirect( self::url( array_key_exists( $tab, self::tabs() ) ? $tab : 'dashboard', array( 'wplh_msg' => 'refreshed' ) ) );
		exit;
	}

	/**
	 * Admin-post: delete analytics data.
	 */
	public static function handle_reset_analytics(): void {
		if ( ! Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wp-live-hype' ), 403 );
		}
		check_admin_referer( 'wplh_reset_analytics' );
		Analytics::reset();
		wp_safe_redirect( self::url( 'analytics', array( 'wplh_msg' => 'analytics_reset' ) ) );
		exit;
	}

	/**
	 * AJAX: country/location preview. Admin-only, nonce-protected, returns
	 * sample data clearly flagged as a preview.
	 */
	public static function ajax_preview(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! Security::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-live-hype' ) ), 403 );
		}
		$country              = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';
		$level                = isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : '';
		$items                = array();
		$previews             = Notifications::preview_items( $country, $level );
		$previews['location'] = Country::is_valid( $country ) ? Synthetic_Engine::preview_location( $country, $level ) : null;
		foreach ( $previews as $type => $item ) {
			$items[ $type ] = is_array( $item ) ? array_merge( Rest_Api::public_fields( $item ), array( 'preview' => true ) ) : null;
		}
		wp_send_json_success(
			array(
				'items'   => $items,
				'mode'    => (string) Settings::get( 'activity_mode' ),
				'country' => Country::name( Country::is_valid( $country ) ? $country : (string) Settings::get( 'target_country' ) ),
			)
		);
	}

	/**
	 * Plugin list action links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'wp-live-hype' ) . '</a>' );
		return $links;
	}

	/**
	 * Subtle developer attribution in the admin footer (plugin page only).
	 *
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! self::is_plugin_screen() || ! Branding::show() ) {
			return $text;
		}
		$name = esc_html( Branding::developer_name() );
		$url  = Branding::support_url();
		if ( '' !== $url ) {
			$name = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $name . '</a>';
		}
		/* translators: %s: developer name (ALWAYS FINAL). */
		return '<span class="wplh-footer-credit">' . sprintf( esc_html__( 'Developed by %s', 'wp-live-hype' ), $name ) . '</span>';
	}

	/**
	 * Plugin version in the admin footer (plugin page only).
	 *
	 * @param string $text Footer text.
	 * @return string
	 */
	public static function footer_version( $text ) {
		if ( ! self::is_plugin_screen() || ! Settings::get( 'branding_show_version' ) ) {
			return $text;
		}
		/* translators: %s: plugin version. */
		return esc_html( sprintf( __( 'Version %s', 'wp-live-hype' ), WPLH_VERSION ) );
	}

	/**
	 * One-time activation notice and action results.
	 */
	public static function notices(): void {
		if ( ! Security::can_manage() ) {
			return;
		}
		if ( get_option( Installer::NOTICE_OPTION ) && ! self::is_plugin_screen() ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( Settings::plugin_label() ) . ' — ' . esc_html__( 'notifications use only real WooCommerce activity. Review your country targeting to finish setup:', 'wp-live-hype' ) . ' <a href="' . esc_url( self::url( 'country' ) ) . '">' . esc_html__( 'Open settings', 'wp-live-hype' ) . '</a></p></div>';
		}
		if ( self::is_plugin_screen() ) {
			delete_option( Installer::NOTICE_OPTION );
		}
	}

	/*
	 * Field helpers (all output escaped).
	 */

	/**
	 * Field name attribute.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function name( string $key ): string {
		return Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Field wrapper start.
	 *
	 * @param string $key   Key (for ids).
	 * @param string $label Label.
	 * @param string $help  Help text.
	 * @param bool   $labelled Whether the label targets an input id.
	 */
	public static function row_start( string $key, string $label, string $help = '', bool $labelled = true ): void {
		echo '<div class="wplh-field" data-field="' . esc_attr( $key ) . '"><div class="wplh-field__label">';
		if ( $labelled ) {
			echo '<label for="wplh-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo '<span class="wplh-field__title" id="wplh-' . esc_attr( $key ) . '-title">' . esc_html( $label ) . '</span>';
		}
		if ( '' !== $help ) {
			echo '<p class="wplh-help" id="wplh-' . esc_attr( $key ) . '-help">' . esc_html( $help ) . '</p>';
		}
		echo '</div><div class="wplh-field__control">';
	}

	/**
	 * Field wrapper end.
	 */
	public static function row_end(): void {
		echo '</div></div>';
	}

	/**
	 * Toggle switch.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	public static function toggle( string $key, string $label, string $help = '' ): void {
		$checked = (bool) Settings::get( $key );
		echo '<div class="wplh-field wplh-field--toggle" data-field="' . esc_attr( $key ) . '">';
		echo '<label class="wplh-switch" for="wplh-' . esc_attr( $key ) . '">';
		echo '<input type="checkbox" role="switch" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="1" ' . checked( $checked, true, false ) . ( '' !== $help ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . ' />';
		echo '<span class="wplh-switch__track" aria-hidden="true"><span class="wplh-switch__thumb"></span></span>';
		echo '<span class="wplh-switch__text"><span class="wplh-switch__label">' . esc_html( $label ) . '</span>';
		if ( '' !== $help ) {
			echo '<span class="wplh-help" id="wplh-' . esc_attr( $key ) . '-help">' . esc_html( $help ) . '</span>';
		}
		echo '</span></label></div>';
	}

	/**
	 * Select.
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options value => label.
	 * @param string $help    Help.
	 * @param array  $attrs   Extra attributes (class, data-*).
	 */
	public static function select( string $key, string $label, array $options, string $help = '', array $attrs = array() ): void {
		self::row_start( $key, $label, $help );
		self::select_control( $key, $options, $attrs, '' !== $help );
		self::row_end();
	}

	/**
	 * Bare select control.
	 *
	 * @param string $key       Key.
	 * @param array  $options   Options.
	 * @param array  $attrs     Attributes.
	 * @param bool   $described Whether help text exists.
	 */
	public static function select_control( string $key, array $options, array $attrs = array(), bool $described = false ): void {
		$value = (string) Settings::get( $key );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::attrs() escapes every name and value.
		echo '<select id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '"' . self::attrs( $attrs ) . ( $described ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . '>';
		foreach ( $options as $option_value => $option_label ) {
			echo '<option value="' . esc_attr( (string) $option_value ) . '" ' . selected( $value, (string) $option_value, false ) . '>' . esc_html( (string) $option_label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Radio group.
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options value => [label, description].
	 * @param string $help    Help.
	 */
	public static function radios( string $key, string $label, array $options, string $help = '' ): void {
		$value = (string) Settings::get( $key );
		self::row_start( $key, $label, $help, false );
		echo '<fieldset class="wplh-radios" aria-labelledby="wplh-' . esc_attr( $key ) . '-title">';
		foreach ( $options as $option_value => $option ) {
			$id = 'wplh-' . $key . '-' . sanitize_html_class( (string) $option_value );
			echo '<label class="wplh-radio" for="' . esc_attr( $id ) . '"><input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) $option_value ) . '" ' . checked( $value, (string) $option_value, false ) . ' />';
			echo '<span><strong>' . esc_html( $option[0] ) . '</strong>';
			if ( ! empty( $option[1] ) ) {
				echo '<span class="wplh-help">' . esc_html( $option[1] ) . '</span>';
			}
			echo '</span></label>';
		}
		echo '</fieldset>';
		self::row_end();
	}

	/**
	 * Number input.
	 *
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @param string $help   Help.
	 * @param string $suffix Unit suffix.
	 */
	public static function number( string $key, string $label, string $help = '', string $suffix = '' ): void {
		self::row_start( $key, $label, $help );
		self::number_control( $key, $suffix, '' !== $help );
		self::row_end();
	}

	/**
	 * Bare number control.
	 *
	 * @param string $key       Key.
	 * @param string $suffix    Suffix.
	 * @param bool   $described Whether help exists.
	 */
	public static function number_control( string $key, string $suffix = '', bool $described = false ): void {
		$field = Settings::fields()[ $key ];
		echo '<span class="wplh-number"><input type="number" class="small-text" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) (int) Settings::get( $key ) ) . '" min="' . esc_attr( (string) $field['min'] ) . '" max="' . esc_attr( (string) $field['max'] ) . '" step="1"' . ( $described ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . ' />';
		if ( '' !== $suffix ) {
			echo '<span class="wplh-suffix">' . esc_html( $suffix ) . '</span>';
		}
		echo '</span>';
	}

	/**
	 * Preset select + custom number (progressive enhancement: the number input
	 * is the submitted field; the select is a helper).
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $presets value => label.
	 * @param string $help    Help.
	 * @param string $suffix  Unit.
	 */
	public static function preset( string $key, string $label, array $presets, string $help = '', string $suffix = '' ): void {
		$value     = (int) Settings::get( $key );
		$is_preset = array_key_exists( $value, $presets );
		self::row_start( $key, $label, $help );
		echo '<div class="wplh-preset" data-preset="' . esc_attr( $key ) . '">';
		echo '<select class="wplh-preset__select" aria-label="' . esc_attr( $label ) . '" hidden>';
		foreach ( $presets as $preset_value => $preset_label ) {
			echo '<option value="' . esc_attr( (string) $preset_value ) . '" ' . selected( $is_preset && $value === (int) $preset_value, true, false ) . '>' . esc_html( $preset_label ) . '</option>';
		}
		echo '<option value="custom" ' . selected( ! $is_preset, true, false ) . '>' . esc_html__( 'Custom…', 'wp-live-hype' ) . '</option>';
		echo '</select>';
		echo '<span class="wplh-preset__custom">';
		self::number_control( $key, $suffix, '' !== $help );
		echo '</span></div>';
		self::row_end();
	}

	/**
	 * Text input.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param string $help        Help.
	 * @param string $placeholder Placeholder.
	 * @param string $type        Input type.
	 */
	public static function text( string $key, string $label, string $help = '', string $placeholder = '', string $type = 'text' ): void {
		self::row_start( $key, $label, $help );
		echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) Settings::get( $key ) ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( '' !== $help ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . ' />';
		self::row_end();
	}

	/**
	 * Color input.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 */
	public static function color( string $key, string $label ): void {
		self::row_start( $key, $label );
		$value = (string) Settings::get( $key );
		echo '<span class="wplh-color"><input type="color" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( $value ) . '" /><code>' . esc_html( $value ) . '</code></span>';
		self::row_end();
	}

	/**
	 * Range slider with output.
	 *
	 * @param string $key    Key.
	 * @param string $label  Label.
	 * @param string $suffix Unit.
	 */
	public static function range( string $key, string $label, string $suffix = 'px' ): void {
		$field = Settings::fields()[ $key ];
		$value = (int) Settings::get( $key );
		self::row_start( $key, $label );
		echo '<span class="wplh-range"><input type="range" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( (string) $field['min'] ) . '" max="' . esc_attr( (string) $field['max'] ) . '" step="1" />';
		echo '<output for="wplh-' . esc_attr( $key ) . '" data-suffix="' . esc_attr( $suffix ) . '">' . esc_html( $value . $suffix ) . '</output></span>';
		self::row_end();
	}

	/**
	 * Checkbox group for multi-choice settings.
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options value => label.
	 * @param string $help    Help.
	 * @param array  $locked  value => reason (rendered disabled, never submitted).
	 */
	public static function checkboxes( string $key, string $label, array $options, string $help = '', array $locked = array() ): void {
		$values = array_map( 'strval', (array) Settings::get( $key ) );
		self::row_start( $key, $label, $help, false );
		echo '<fieldset class="wplh-checks" aria-labelledby="wplh-' . esc_attr( $key ) . '-title">';
		foreach ( $options as $option_value => $option_label ) {
			$id = 'wplh-' . $key . '-' . sanitize_html_class( (string) $option_value );
			echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::name( $key ) ) . '[]" value="' . esc_attr( (string) $option_value ) . '" ' . checked( in_array( (string) $option_value, $values, true ), true, false ) . ' /> ' . esc_html( (string) $option_label ) . '</label>';
		}
		foreach ( $locked as $locked_label => $reason ) {
			echo '<label class="wplh-locked"><input type="checkbox" disabled /> ' . esc_html( (string) $locked_label ) . ' <em>' . esc_html( (string) $reason ) . '</em></label>';
		}
		echo '</fieldset>';
		self::row_end();
	}

	/**
	 * Textarea.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param string $help        Help.
	 * @param string $value       Value to show.
	 * @param int    $rows        Rows.
	 * @param string $placeholder Placeholder.
	 */
	public static function textarea( string $key, string $label, string $help, string $value, int $rows = 4, string $placeholder = '' ): void {
		self::row_start( $key, $label, $help );
		echo '<textarea class="large-text code" id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" rows="' . esc_attr( (string) $rows ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( '' !== $help ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . '>' . esc_textarea( $value ) . '</textarea>';
		self::row_end();
	}

	/**
	 * Single searchable country select (WooCommerce data).
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	public static function country( string $key, string $label, string $help = '' ): void {
		$value = (string) Settings::get( $key );
		self::row_start( $key, $label, $help );
		echo '<select id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '" class="wc-enhanced-select wplh-country-select" data-placeholder="' . esc_attr__( 'Choose a country…', 'wp-live-hype' ) . '" data-allow_clear="true" style="min-width:320px"' . ( '' !== $help ? ' aria-describedby="wplh-' . esc_attr( $key ) . '-help"' : '' ) . '>';
		echo '<option value="">' . esc_html__( '— Select a country —', 'wp-live-hype' ) . '</option>';
		foreach ( Country::countries() as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $value, $code, false ) . '>' . esc_html( $name . ' (' . $code . ')' ) . '</option>';
		}
		echo '</select>';
		self::row_end();
	}

	/**
	 * Multiple searchable country select.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	public static function countries( string $key, string $label, string $help = '' ): void {
		$values = (array) Settings::get( $key );
		self::row_start( $key, $label, $help );
		echo '<input type="hidden" name="' . esc_attr( self::name( $key ) ) . '[]" value="" />';
		echo '<select multiple id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '[]" class="wc-enhanced-select" data-placeholder="' . esc_attr__( 'Choose countries…', 'wp-live-hype' ) . '" style="min-width:320px">';
		foreach ( Country::countries() as $code => $name ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( in_array( $code, $values, true ), true, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select>';
		self::row_end();
	}

	/**
	 * AJAX product search (WooCommerce).
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	public static function products( string $key, string $label, string $help = '' ): void {
		$ids = array_map( 'absint', (array) Settings::get( $key ) );
		self::row_start( $key, $label, $help );
		echo '<input type="hidden" name="' . esc_attr( self::name( $key ) ) . '[]" value="" />';
		echo '<select multiple id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '[]" class="wc-product-search" data-action="woocommerce_json_search_products" data-placeholder="' . esc_attr__( 'Search for a product…', 'wp-live-hype' ) . '" style="min-width:320px">';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				echo '<option value="' . esc_attr( (string) $id ) . '" selected>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select>';
		self::row_end();
	}

	/**
	 * AJAX category search (WooCommerce).
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 */
	public static function categories( string $key, string $label, string $help = '' ): void {
		$ids = array_map( 'absint', (array) Settings::get( $key ) );
		self::row_start( $key, $label, $help );
		echo '<input type="hidden" name="' . esc_attr( self::name( $key ) ) . '[]" value="" />';
		echo '<select multiple id="wplh-' . esc_attr( $key ) . '" name="' . esc_attr( self::name( $key ) ) . '[]" class="wc-category-search" data-return_id="true" data-allow_clear="true" data-minimum_input_length="1" data-placeholder="' . esc_attr__( 'Search for a category…', 'wp-live-hype' ) . '" style="min-width:320px">';
		foreach ( $ids as $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				echo '<option value="' . esc_attr( (string) $id ) . '" selected>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select>';
		self::row_end();
	}

	/**
	 * Render extra HTML attributes.
	 *
	 * @param array $attrs Attributes.
	 * @return string
	 */
	private static function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	/**
	 * Settings form opening (Settings API handles the nonce).
	 *
	 * @param string $tab Tab.
	 */
	public static function form_start( string $tab ): void {
		echo '<form method="post" action="options.php" class="wplh-form" novalidate>';
		settings_fields( Settings::GROUP );
		echo '<input type="hidden" name="' . esc_attr( self::name( '_tab' ) ) . '" value="' . esc_attr( $tab ) . '" />';
	}

	/**
	 * Settings form closing.
	 */
	public static function form_end(): void {
		echo '<div class="wplh-form__footer">';
		submit_button( __( 'Save changes', 'wp-live-hype' ), 'primary', 'submit', false );
		echo '</div></form>';
	}

	/**
	 * Card open.
	 *
	 * @param string $title Title.
	 * @param string $intro Intro paragraph.
	 * @param string $extra_class Extra class.
	 */
	public static function card_start( string $title, string $intro = '', string $extra_class = '' ): void {
		echo '<section class="wplh-card ' . esc_attr( $extra_class ) . '">';
		if ( '' !== $title ) {
			echo '<h2 class="wplh-card__title">' . esc_html( $title ) . '</h2>';
		}
		if ( '' !== $intro ) {
			echo '<p class="wplh-card__intro">' . esc_html( $intro ) . '</p>';
		}
	}

	/**
	 * Card close.
	 */
	public static function card_end(): void {
		echo '</section>';
	}

	/**
	 * Preview types the current activity mode can actually produce.
	 *
	 * @return array<string,string> type => label.
	 */
	public static function preview_types(): array {
		$all = array(
			'purchase' => __( 'Purchase', 'wp-live-hype' ),
			'featured' => __( 'Featured', 'wp-live-hype' ),
			'explore'  => __( 'Spotlight', 'wp-live-hype' ),
			'location' => __( 'Shipping', 'wp-live-hype' ),
			'sale'     => __( 'Sale', 'wp-live-hype' ),
			'popular'  => __( 'Popular', 'wp-live-hype' ),
		);
		switch ( (string) Settings::get( 'activity_mode' ) ) {
			case 'aggregate':
				$keys = array( 'purchase', 'sale', 'popular' );
				break;
			case 'hybrid':
				$keys = array( 'purchase', 'featured', 'explore', 'location', 'sale', 'popular' );
				break;
			default:
				$keys = array( 'featured', 'explore', 'location', 'sale' );
		}
		return array_intersect_key( $all, array_flip( $keys ) );
	}

	/**
	 * Preview panel markup (filled by admin.js), limited to the types the
	 * current activity mode can produce.
	 *
	 * @param string $id   Panel id.
	 * @param bool   $live Whether it follows unsaved Display form fields.
	 */
	public static function preview_panel( string $id, bool $live = false ): void {
		$types = self::preview_types();
		$first = (string) key( $types );
		?>
		<div class="wplh-preview" id="<?php echo esc_attr( $id ); ?>" data-live="<?php echo $live ? '1' : '0'; ?>" data-type="<?php echo esc_attr( $first ); ?>">
			<div class="wplh-preview__banner" role="note"><?php esc_html_e( 'SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY', 'wp-live-hype' ); ?></div>
			<div class="wplh-preview__toolbar">
				<div class="wplh-segmented" role="group" aria-label="<?php esc_attr_e( 'Device', 'wp-live-hype' ); ?>">
					<button type="button" class="wplh-seg is-active" data-device="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'wp-live-hype' ); ?></button>
					<button type="button" class="wplh-seg" data-device="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'wp-live-hype' ); ?></button>
				</div>
				<div class="wplh-segmented" role="group" aria-label="<?php esc_attr_e( 'Notification type', 'wp-live-hype' ); ?>">
					<?php foreach ( $types as $type => $label ) : ?>
						<button type="button" class="wplh-seg <?php echo $type === $first ? 'is-active' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>" aria-pressed="<?php echo $type === $first ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="wplh-preview__stage" data-device="desktop">
				<div class="wplh-preview__frame" aria-hidden="true">
					<div class="wplh-mock"><span></span><span></span><span></span><span></span></div>
				</div>
				<div class="wplh-preview__slot"></div>
				<p class="wplh-preview__empty" hidden></p>
			</div>
		</div>
		<?php
	}
}
