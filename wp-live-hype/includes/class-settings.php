<?php
/**
 * Settings schema, defaults, access and sanitization.
 *
 * Every option the plugin stores lives in a single autoloaded array option.
 * The schema below is the single source of truth for defaults, the admin tab a
 * field belongs to, and how submitted values are validated.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Settings registry.
 */
final class Settings {

	const OPTION = 'wplh_settings';
	const GROUP  = 'wplh_settings_group';

	/**
	 * Order statuses that can never be treated as a purchase.
	 */
	const BLOCKED_STATUSES = array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft', 'trash', 'draft', 'auto-draft' );

	/**
	 * Request-level memo of the merged settings.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * Field schema.
	 *
	 * @return array<string,array>
	 */
	public static function fields(): array {
		static $fields = null;
		if ( null !== $fields ) {
			return $fields;
		}

		$fields = array(
			// General.
			'enabled'                    => array(
				'tab'     => 'general',
				'type'    => 'bool',
				'default' => true,
			),
			'admin_only'                 => array(
				'tab'     => 'general',
				'type'    => 'bool',
				'default' => false,
			),

			// Country targeting.
			'country_filter'             => array(
				'tab'     => 'country',
				'type'    => 'bool',
				'default' => true,
			),
			'target_country'             => array(
				'tab'     => 'country',
				'type'    => 'country',
				'default' => '',
			),
			'country_source'             => array(
				'tab'     => 'country',
				'type'    => 'choice',
				'default' => 'billing',
				'choices' => array( 'billing', 'shipping', 'shipping_billing' ),
			),
			'unfiltered_scope'           => array(
				'tab'     => 'country',
				'type'    => 'choice',
				'default' => 'all',
				'choices' => array( 'all', 'base', 'countries', 'none' ),
			),
			'allowed_countries'          => array(
				'tab'     => 'country',
				'type'    => 'countries',
				'default' => array(),
			),
			'location_level'             => array(
				'tab'     => 'country',
				'type'    => 'choice',
				'default' => 'region',
				'choices' => array( 'none', 'country', 'region', 'city', 'city_region', 'city_country' ),
			),

			// Live Hype engine.
			'activity_mode'              => array(
				'tab'     => 'engine',
				'type'    => 'choice',
				'default' => 'synthetic',
				'choices' => array( 'synthetic', 'hybrid', 'aggregate' ),
			),
			'use_aggregate'              => array(
				'tab'     => 'engine',
				'type'    => 'bool',
				'default' => false,
			),
			'promo_featured'             => array(
				'tab'     => 'engine',
				'type'    => 'bool',
				'default' => true,
			),
			'promo_explore'              => array(
				'tab'     => 'engine',
				'type'    => 'bool',
				'default' => true,
			),
			'promo_location'             => array(
				'tab'     => 'engine',
				'type'    => 'bool',
				'default' => true,
			),
			'promo_sale'                 => array(
				'tab'     => 'engine',
				'type'    => 'bool',
				'default' => true,
			),
			'promo_ratio'                => array(
				'tab'     => 'engine',
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 5,
			),
			'cooldown_minutes'           => array(
				'tab'     => 'engine',
				'type'    => 'int',
				'default' => 30,
				'min'     => 1,
				'max'     => 1440,
			),

			// Weighting.
			'product_weights'            => array(
				'tab'     => 'weighting',
				'type'    => 'weightmap',
				'default' => array(),
				'key'     => '/^[0-9]{1,20}$/',
				'max'     => 4,
				'min'     => 1,
			),
			'region_weights'             => array(
				'tab'     => 'weighting',
				'type'    => 'weightmap',
				'default' => array(),
				'key'     => '/^[A-Z]{2}:[A-Z0-9\-]{1,10}$/',
				'max'     => 3,
				'min'     => 0,
			),
			'city_weights'               => array(
				'tab'     => 'weighting',
				'type'    => 'weightmap',
				'default' => array(),
				'key'     => '/^[A-Z]{2}:[a-z0-9\-]{1,60}$/',
				'max'     => 3,
				'min'     => 0,
			),

			// Sound.
			'sound_desktop'              => array(
				'tab'     => 'sound',
				'type'    => 'bool',
				'default' => false,
			),
			'sound_mobile'               => array(
				'tab'     => 'sound',
				'type'    => 'bool',
				'default' => false,
			),
			'sound_volume'               => array(
				'tab'     => 'sound',
				'type'    => 'int',
				'default' => 35,
				'min'     => 0,
				'max'     => 100,
			),
			'sound_choice'               => array(
				'tab'     => 'sound',
				'type'    => 'choice',
				'default' => 'chime',
				'choices' => array( 'chime', 'modern', 'pop', 'clean' ),
			),

			// Notification types & templates.
			'type_purchase'              => array(
				'tab'     => 'notifications',
				'type'    => 'bool',
				'default' => true,
			),
			'type_product_purchase'      => array(
				'tab'     => 'notifications',
				'type'    => 'bool',
				'default' => true,
			),
			'type_sale'                  => array(
				'tab'     => 'notifications',
				'type'    => 'bool',
				'default' => true,
			),
			'type_popular'               => array(
				'tab'     => 'notifications',
				'type'    => 'bool',
				'default' => false,
			),
			'priority_mode'              => array(
				'tab'     => 'notifications',
				'type'    => 'choice',
				'default' => 'priority',
				'choices' => array( 'priority', 'weighted' ),
			),
			'weight_product_purchase'    => array(
				'tab'     => 'notifications',
				'type'    => 'int',
				'default' => 10,
				'min'     => 1,
				'max'     => 10,
			),
			'weight_purchase'            => array(
				'tab'     => 'notifications',
				'type'    => 'int',
				'default' => 8,
				'min'     => 1,
				'max'     => 10,
			),
			'weight_sale'                => array(
				'tab'     => 'notifications',
				'type'    => 'int',
				'default' => 5,
				'min'     => 1,
				'max'     => 10,
			),
			'weight_popular'             => array(
				'tab'     => 'notifications',
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 10,
			),
			'template_rotation'          => array(
				'tab'     => 'notifications',
				'type'    => 'choice',
				'default' => 'first',
				'choices' => array( 'first', 'random' ),
			),
			'tpl_purchase'               => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'purchase',
			),
			'tpl_sale'                   => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'sale',
			),
			'tpl_popular'                => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'popular',
			),
			'tpl_bestseller'             => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'bestseller',
			),
			'label_purchase'             => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'purchase',
			),
			'label_sale'                 => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'sale',
			),
			'label_popular'              => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'popular',
			),
			'tpl_featured'               => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'featured',
			),
			'tpl_explore'                => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'explore',
			),
			'tpl_location'               => array(
				'tab'      => 'notifications',
				'type'     => 'template',
				'default'  => '',
				'template' => 'location',
			),
			'label_featured'             => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'featured',
			),
			'label_explore'              => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'explore',
			),
			'label_location'             => array(
				'tab'     => 'notifications',
				'type'    => 'label',
				'default' => '',
				'label'   => 'location',
			),

			// Display.
			'position_desktop'           => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'bottom-left',
				'choices' => array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ),
			),
			'position_mobile'            => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'bottom-center',
				'choices' => array( 'bottom-center', 'bottom-left', 'top-center', 'hidden' ),
			),
			'animation'                  => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'slidefade',
				'choices' => array( 'none', 'fade', 'slide', 'slidefade', 'scale' ),
			),
			'theme'                      => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'light',
				'choices' => array( 'light', 'dark', 'auto', 'custom' ),
			),
			'accent_color'               => array(
				'tab'     => 'display',
				'type'    => 'color',
				'default' => '#16a34a',
			),
			'bg_color'                   => array(
				'tab'     => 'display',
				'type'    => 'color',
				'default' => '#ffffff',
			),
			'text_color'                 => array(
				'tab'     => 'display',
				'type'    => 'color',
				'default' => '#111827',
			),
			'radius'                     => array(
				'tab'     => 'display',
				'type'    => 'int',
				'default' => 14,
				'min'     => 0,
				'max'     => 32,
			),
			'shadow'                     => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'soft',
				'choices' => array( 'none', 'soft', 'medium', 'strong' ),
			),
			'width'                      => array(
				'tab'     => 'display',
				'type'    => 'int',
				'default' => 340,
				'min'     => 260,
				'max'     => 440,
			),
			'offset_x'                   => array(
				'tab'     => 'display',
				'type'    => 'int',
				'default' => 20,
				'min'     => 0,
				'max'     => 120,
			),
			'offset_y'                   => array(
				'tab'     => 'display',
				'type'    => 'int',
				'default' => 20,
				'min'     => 0,
				'max'     => 160,
			),
			'z_index'                    => array(
				'tab'     => 'display',
				'type'    => 'int',
				'default' => 99990,
				'min'     => 1,
				'max'     => 2147483000,
			),
			'font'                       => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'inherit',
				'choices' => array( 'inherit', 'system' ),
			),
			'show_image'                 => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'image_shape'                => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'rounded',
				'choices' => array( 'rounded', 'square', 'circle' ),
			),
			'show_label'                 => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'show_time'                  => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'time_display'               => array(
				'tab'     => 'display',
				'type'    => 'choice',
				'default' => 'relative',
				'choices' => array( 'relative', 'approximate', 'recently' ),
			),
			'show_verified'              => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'show_close'                 => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'show_sale_price'            => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'link_to_product'            => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),
			'a11y_announce'              => array(
				'tab'     => 'display',
				'type'    => 'bool',
				'default' => true,
			),

			// Frequency.
			'first_delay'                => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 10,
				'min'     => 0,
				'max'     => 600,
			),
			'duration'                   => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 8,
				'min'     => 3,
				'max'     => 60,
			),
			'interval'                   => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 20,
				'min'     => 5,
				'max'     => 3600,
			),
			'interval_max'               => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 90,
				'min'     => 5,
				'max'     => 3600,
			),
			'random_timing'              => array(
				'tab'     => 'frequency',
				'type'    => 'bool',
				'default' => true,
			),
			'max_per_session'            => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 5,
				'min'     => 0,
				'max'     => 100,
			),
			'max_per_page'               => array(
				'tab'     => 'frequency',
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 10,
			),
			'dismiss_behavior'           => array(
				'tab'     => 'frequency',
				'type'    => 'choice',
				'default' => 'session',
				'choices' => array( 'session', 'page', 'none' ),
			),
			'pause_on_hover'             => array(
				'tab'     => 'frequency',
				'type'    => 'bool',
				'default' => true,
			),

			// Products & page targeting.
			'display_on'                 => array(
				'tab'     => 'products',
				'type'    => 'choice',
				'default' => 'everywhere',
				'choices' => array( 'everywhere', 'selected' ),
			),
			'pages'                      => array(
				'tab'     => 'products',
				'type'    => 'multichoice',
				'default' => array( 'home', 'shop', 'product', 'category' ),
				'choices' => array( 'home', 'shop', 'product', 'category', 'tag', 'cart', 'checkout', 'page', 'post', 'other' ),
			),
			'exclude_account'            => array(
				'tab'     => 'products',
				'type'    => 'bool',
				'default' => true,
			),
			'exclude_login'              => array(
				'tab'     => 'products',
				'type'    => 'bool',
				'default' => true,
			),
			'exclude_checkout'           => array(
				'tab'     => 'products',
				'type'    => 'bool',
				'default' => true,
			),
			'exclude_cart'               => array(
				'tab'     => 'products',
				'type'    => 'bool',
				'default' => false,
			),
			'exclude_on_products'        => array(
				'tab'     => 'products',
				'type'    => 'ids',
				'default' => array(),
			),
			'exclude_on_categories'      => array(
				'tab'     => 'products',
				'type'    => 'ids',
				'default' => array(),
			),
			'exclude_urls'               => array(
				'tab'     => 'products',
				'type'    => 'patterns',
				'default' => '',
			),
			'hide_products'              => array(
				'tab'     => 'products',
				'type'    => 'ids',
				'default' => array(),
			),
			'hide_categories'            => array(
				'tab'     => 'products',
				'type'    => 'ids',
				'default' => array(),
			),
			'only_categories'            => array(
				'tab'     => 'products',
				'type'    => 'ids',
				'default' => array(),
			),
			'skip_out_of_stock'          => array(
				'tab'     => 'products',
				'type'    => 'bool',
				'default' => true,
			),

			// Data sources.
			'order_statuses'             => array(
				'tab'     => 'data',
				'type'    => 'statuses',
				'default' => array( 'processing', 'completed' ),
			),
			'lookback_hours'             => array(
				'tab'     => 'data',
				'type'    => 'intchoice',
				'default' => 168,
				'choices' => array( 6, 12, 24, 72, 168, 336, 720 ),
			),
			'max_records'                => array(
				'tab'     => 'data',
				'type'    => 'int',
				'default' => 50,
				'min'     => 5,
				'max'     => 200,
			),
			'popular_source'             => array(
				'tab'     => 'data',
				'type'    => 'choice',
				'default' => '7d',
				'choices' => array( '24h', '7d', '30d', 'lifetime' ),
			),
			'popular_min_sales'          => array(
				'tab'     => 'data',
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 1000,
			),
			'popular_count'              => array(
				'tab'     => 'data',
				'type'    => 'int',
				'default' => 5,
				'min'     => 1,
				'max'     => 20,
			),
			'popular_products'           => array(
				'tab'     => 'data',
				'type'    => 'ids',
				'default' => array(),
			),

			// Analytics.
			'analytics_enabled'          => array(
				'tab'     => 'analytics',
				'type'    => 'bool',
				'default' => true,
			),
			'analytics_retention'        => array(
				'tab'     => 'analytics',
				'type'    => 'intchoice',
				'default' => 90,
				'choices' => array( 30, 90, 180, 365 ),
			),

			// Advanced.
			'cache_ttl'                  => array(
				'tab'     => 'advanced',
				'type'    => 'intchoice',
				'default' => 5,
				'choices' => array( 1, 5, 10, 30 ),
			),
			'scan_limit'                 => array(
				'tab'     => 'advanced',
				'type'    => 'int',
				'default' => 300,
				'min'     => 50,
				'max'     => 1000,
			),
			'client_cache'               => array(
				'tab'     => 'advanced',
				'type'    => 'bool',
				'default' => true,
			),
			'debug'                      => array(
				'tab'     => 'advanced',
				'type'    => 'bool',
				'default' => false,
			),
			'uninstall_delete_settings'  => array(
				'tab'     => 'advanced',
				'type'    => 'bool',
				'default' => false,
			),
			'uninstall_delete_cache'     => array(
				'tab'     => 'advanced',
				'type'    => 'bool',
				'default' => true,
			),
			'uninstall_delete_analytics' => array(
				'tab'     => 'advanced',
				'type'    => 'bool',
				'default' => false,
			),

			// Branding / white-label.
			'branding_show'              => array(
				'tab'     => 'branding',
				'type'    => 'bool',
				'default' => true,
			),
			'branding_label'             => array(
				'tab'     => 'branding',
				'type'    => 'text',
				'default' => '',
				'max'     => 60,
			),
			'branding_developer'         => array(
				'tab'     => 'branding',
				'type'    => 'text',
				'default' => 'ALWAYS FINAL',
				'max'     => 60,
			),
			'branding_support_url'       => array(
				'tab'     => 'branding',
				'type'    => 'url',
				'default' => '',
			),
			'branding_logo_url'          => array(
				'tab'     => 'branding',
				'type'    => 'url',
				'default' => '',
			),
			'branding_show_version'      => array(
				'tab'     => 'branding',
				'type'    => 'bool',
				'default' => true,
			),
			'frontend_attribution'       => array(
				'tab'     => 'branding',
				'type'    => 'bool',
				'default' => false,
			),
		);

		return $fields;
	}

	/**
	 * Default values for every field.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		$defaults = array();
		foreach ( self::fields() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}
		return $defaults;
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null === self::$memo ) {
			$stored     = get_option( self::OPTION, array() );
			$stored     = is_array( $stored ) ? $stored : array();
			self::$memo = array_merge( self::defaults(), array_intersect_key( $stored, self::fields() ) );
		}
		return self::$memo;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Clear the request memo (after the option changes).
	 */
	public static function flush(): void {
		self::$memo = null;
	}

	/**
	 * Fields belonging to a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return array
	 */
	public static function fields_for_tab( string $tab ): array {
		return array_filter(
			self::fields(),
			static function ( $field ) use ( $tab ) {
				return $field['tab'] === $tab;
			}
		);
	}

	/**
	 * Register the option with the Settings API.
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * Admin forms submit one tab at a time and include a hidden `_tab` value; only
	 * fields from that tab are touched so other tabs keep their stored values.
	 * Programmatic updates (no `_tab`) sanitize whatever keys are present.
	 *
	 * @param mixed $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize( $input ): array {
		$stored  = get_option( self::OPTION, array() );
		$current = array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::fields() ) : array() );

		if ( ! is_array( $input ) ) {
			return $current;
		}

		$tab = isset( $input['_tab'] ) ? sanitize_key( (string) $input['_tab'] ) : '';

		foreach ( self::fields() as $key => $field ) {
			if ( '' !== $tab ) {
				if ( $field['tab'] !== $tab ) {
					continue;
				}
				// Unchecked checkboxes / emptied multi-selects are absent from a form post.
				$raw = array_key_exists( $key, $input ) ? $input[ $key ] : null;
				if ( null === $raw && ! in_array( $field['type'], array( 'bool', 'ids', 'countries', 'multichoice', 'statuses' ), true ) ) {
					continue;
				}
			} else {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}
				$raw = $input[ $key ];
			}

			$current[ $key ] = self::sanitize_field( $key, $field, $raw, $current[ $key ] );
		}

		// Cross-field rules.
		if ( (int) $current['interval_max'] < (int) $current['interval'] ) {
			$current['interval_max'] = (int) $current['interval'];
		}
		if ( $current['country_filter'] && '' === $current['target_country'] && 'country' === $tab ) {
			add_settings_error( self::OPTION, 'wplh_no_country', __( 'Country filtering is ON but no target country is selected. Purchase notifications will remain inactive until a country is chosen.', 'wp-live-hype' ), 'warning' );
		}
		if ( 'countries' === $current['unfiltered_scope'] && empty( $current['allowed_countries'] ) && 'country' === $tab && ! $current['country_filter'] ) {
			add_settings_error( self::OPTION, 'wplh_no_countries', __( 'No countries were selected for the "specific countries" scope. Purchase notifications will remain inactive until at least one country is selected.', 'wp-live-hype' ), 'warning' );
		}

		return $current;
	}

	/**
	 * Sanitize a single field value.
	 *
	 * @param string $key      Field key.
	 * @param array  $field    Field schema.
	 * @param mixed  $raw      Raw value.
	 * @param mixed  $previous Previous value (kept when input is invalid).
	 * @return mixed
	 */
	private static function sanitize_field( string $key, array $field, $raw, $previous ) {
		switch ( $field['type'] ) {
			case 'bool':
				return ! empty( $raw ) && 'no' !== $raw && '0' !== $raw && 'false' !== $raw;

			case 'int':
				if ( ! is_scalar( $raw ) || ! is_numeric( $raw ) ) {
					return $previous;
				}
				return max( (int) $field['min'], min( (int) $field['max'], (int) $raw ) );

			case 'intchoice':
				$value = is_scalar( $raw ) ? (int) $raw : -1;
				return in_array( $value, $field['choices'], true ) ? $value : $previous;

			case 'choice':
				$value = is_scalar( $raw ) ? (string) $raw : '';
				return in_array( $value, $field['choices'], true ) ? $value : $previous;

			case 'multichoice':
				$values = is_array( $raw ) ? array_map( 'strval', $raw ) : array();
				return array_values( array_intersect( $field['choices'], $values ) );

			case 'color':
				$value = is_scalar( $raw ) ? sanitize_hex_color( (string) $raw ) : '';
				return $value ? $value : $previous;

			case 'text':
				$value = is_scalar( $raw ) ? Security::clean_text( (string) $raw, (int) ( $field['max'] ?? 200 ) ) : '';
				return $value;

			case 'url':
				$value = is_scalar( $raw ) ? esc_url_raw( trim( (string) $raw ), array( 'http', 'https' ) ) : '';
				return $value;

			case 'ids':
				$ids = is_array( $raw ) ? $raw : ( is_scalar( $raw ) && '' !== $raw ? explode( ',', (string) $raw ) : array() );
				$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
				return array_slice( $ids, 0, 500 );

			case 'country':
				$value = is_scalar( $raw ) ? strtoupper( sanitize_text_field( (string) $raw ) ) : '';
				if ( '' === $value ) {
					return '';
				}
				return Country::is_valid( $value ) ? $value : $previous;

			case 'countries':
				$values = is_array( $raw ) ? $raw : array();
				$clean  = array();
				foreach ( $values as $value ) {
					$value = is_scalar( $value ) ? strtoupper( sanitize_text_field( (string) $value ) ) : '';
					if ( '' !== $value && Country::is_valid( $value ) ) {
						$clean[] = $value;
					}
				}
				return array_values( array_unique( $clean ) );

			case 'statuses':
				$values  = is_array( $raw ) ? $raw : array();
				$allowed = array_keys( self::selectable_order_statuses() );
				$clean   = array();
				foreach ( $values as $value ) {
					$value = is_scalar( $value ) ? self::normalize_status( (string) $value ) : '';
					if ( in_array( $value, $allowed, true ) ) {
						$clean[] = $value;
					}
				}
				$clean = array_values( array_unique( $clean ) );
				if ( empty( $clean ) ) {
					add_settings_error( self::OPTION, 'wplh_statuses', __( 'At least one qualifying order status is required. The previous selection was kept.', 'wp-live-hype' ) );
					return $previous;
				}
				return $clean;

			case 'patterns':
				return Security::sanitize_url_patterns( is_scalar( $raw ) ? (string) $raw : '' );

			case 'template':
				return Templates::sanitize_setting( $field['template'], is_scalar( $raw ) ? (string) $raw : '', (string) $previous );

			case 'label':
				$value = is_scalar( $raw ) ? Security::clean_text( (string) $raw, 40 ) : '';
				return Templates::default_label( $field['label'] ) === $value ? '' : $value;

			case 'weightmap':
				// Forms submit one country / product page at a time: merge into the stored map.
				$map = is_array( $previous ) ? $previous : array();
				if ( is_array( $raw ) ) {
					foreach ( $raw as $map_key => $level ) {
						$map_key = (string) $map_key;
						if ( ! preg_match( $field['key'], $map_key ) || ! is_scalar( $level ) || ! is_numeric( $level ) ) {
							continue;
						}
						$level = max( (int) $field['min'], min( (int) $field['max'], (int) $level ) );
						if ( Locations::default_level_for_key( $key, $map_key ) === $level ) {
							unset( $map[ $map_key ] ); // Normal is the default: keep the option small.
						} else {
							$map[ $map_key ] = $level;
						}
					}
				}
				return array_slice( $map, -3000, null, true );
		}

		return $previous;
	}

	/**
	 * Strip the `wc-` prefix from an order status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/**
	 * Order statuses an administrator may treat as qualifying purchases.
	 *
	 * Statuses that never represent a completed purchase (pending payment,
	 * failed, cancelled, refunded, drafts, trash) are never offered.
	 *
	 * @return array<string,string> status (without prefix) => label.
	 */
	public static function selectable_order_statuses(): array {
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$out      = array();
		foreach ( $statuses as $key => $label ) {
			$status = self::normalize_status( (string) $key );
			if ( in_array( $status, self::BLOCKED_STATUSES, true ) ) {
				continue;
			}
			$out[ $status ] = (string) $label;
		}
		return $out;
	}

	/**
	 * Qualifying order statuses after re-validating against the blocked list.
	 *
	 * @return string[]
	 */
	public static function qualifying_statuses(): array {
		$statuses = (array) self::get( 'order_statuses' );
		$statuses = array_map( array( __CLASS__, 'normalize_status' ), $statuses );
		$statuses = array_diff( $statuses, self::BLOCKED_STATUSES );
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * Plugin label shown in the admin (white-label aware).
	 *
	 * @return string
	 */
	public static function plugin_label(): string {
		$label = (string) self::get( 'branding_label' );
		return '' !== $label ? $label : __( 'WP Live Hype', 'wp-live-hype' );
	}
}
